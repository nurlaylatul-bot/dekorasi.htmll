<?php

declare(strict_types=1);

namespace AllCoach\Modules\PayPal;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Order\ConfirmOrderPaymentService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Infrastructure\Privacy\ConsentPolicy;
use AllCoach\Modules\PayPal\PayPalPaymentGateway;

class PayPalOrderController extends RestController {

	protected string $restBase = 'orders';

	public function __construct(
		private readonly PayPalPaymentGateway $paypalGateway,
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly ConfirmOrderPaymentService $confirmService,
		private readonly OrderQueryService $queryService,
		private readonly ConsentPolicy $consentPolicy,
		private readonly SettingsManager $settingsManager,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/paypal/test-connection',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'testConnection' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'client_id' => $this->argString(
							description: __( 'PayPal REST API client ID.', 'allcoach' ),
							required: true,
						),
						'secret'    => $this->argString(
							description: __( 'PayPal REST API secret.', 'allcoach' ),
							required: true,
						),
						'sandbox'   => [
							'description'       => __( 'Use sandbox environment.', 'allcoach' ),
							'type'              => 'boolean',
							'required'          => false,
							'default'           => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/paypal-order',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createPaypalOrder' ],
					'permission_callback' => '__return_true',
					'args'                => $this->idArg(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/paypal-capture',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'capturePaypalOrder' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge(
						$this->idArg(),
						[
							'paypal_order_id' => $this->argString(
								description: __( 'PayPal order ID returned after buyer approval.', 'allcoach' ),
								required: true,
							),
							'consent'         => [
								'description' => __( 'Whether the privacy policy is currently accepted.', 'allcoach' ),
								'type'        => 'boolean',
								'required'    => false,
							],
						]
					),
				],
			]
		);
	}

	public function createPaypalOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id    = (int) $request->get_param( 'id' );
		$order = $this->orderRepository->findById( $id );

		if ( ! $order ) {
			return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
		}

		if ( ! $this->canAccessPaypalOrder( $order ) ) {
			return $this->forbiddenResponse();
		}

		if ( $order->getPaymentMethod() !== 'paypal' ) {
			return $this->errorResponse( __( 'Order payment method is not PayPal.', 'allcoach' ), 422 );
		}

		if ( ! $order->getStatus()->isPending() ) {
			return $this->errorResponse( __( 'Order is not pending payment.', 'allcoach' ), 422 );
		}

		$orderItem = $this->orderItemRepository->findByOrderId( $id )[0] ?? null;
		if ( ! $orderItem ) {
			return $this->errorResponse( __( 'Order item not found.', 'allcoach' ), 422 );
		}

		$paypalOrderId = $this->paypalGateway->createOrder( $order, $orderItem );
		if ( $paypalOrderId === null ) {
			return $this->errorResponse( __( 'Failed to create PayPal order. Check your API credentials.', 'allcoach' ), 502 );
		}

		$order->setPaymentReference( $paypalOrderId );
		$this->orderRepository->save( $order );

		return $this->successResponse( [ 'paypal_order_id' => $paypalOrderId ] );
	}

	public function capturePaypalOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id            = (int) $request->get_param( 'id' );
		$paypalOrderId = (string) $request->get_param( 'paypal_order_id' );

		$order = $this->orderRepository->findById( $id );
		if ( ! $order ) {
			return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
		}

		if ( ! $this->canAccessPaypalOrder( $order, $paypalOrderId ) ) {
			return $this->forbiddenResponse();
		}

		if ( $order->getPaymentMethod() !== 'paypal' ) {
			return $this->errorResponse( __( 'Order payment method is not PayPal.', 'allcoach' ), 422 );
		}

		if ( ! $order->getStatus()->isPending() ) {
			return $this->errorResponse( __( 'Order is not pending payment.', 'allcoach' ), 422 );
		}

		if ( $this->consentPolicy->isRequired() && ( ! $order->hasConsent() || empty( $request->get_param( 'consent' ) ) ) ) {
			return $this->errorResponse( __( 'You must accept the privacy policy to continue.', 'allcoach' ), 422 );
		}

		$result = $this->paypalGateway->completeOrder( $paypalOrderId );
		if ( ! $result['success'] ) {
			return $this->errorResponse( __( 'PayPal payment could not be completed.', 'allcoach' ), 502 );
		}

		if ( $result['action'] === 'authorize' ) {
			$order->setPaymentReference( 'AUTH:' . $result['transaction_id'] );
			$this->orderRepository->save( $order );

			$view = $this->queryService->findById( $id );
			if ( ! $view ) {
				return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
			}

			return $this->successResponse( $this->prepareView( $view ) );
		}

		try {
			$view = $this->confirmService->execute( $id, $result['transaction_id'] );
			return $this->successResponse( $this->prepareView( $view ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function testConnection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$clientId = (string) $request->get_param( 'client_id' );
		$secret   = (string) $request->get_param( 'secret' );
		$sandbox  = (bool) $request->get_param( 'sandbox' );

		$result = $this->paypalGateway->testConnection( $clientId, $secret, $sandbox );

		return $this->successResponse( $result );
	}

	private function canAccessPaypalOrder( Order $order, ?string $paypalOrderId = null ): bool {
		if ( $paypalOrderId !== null && $paypalOrderId !== '' && ! hash_equals( (string) $order->getPaymentReference(), $paypalOrderId ) ) {
			return false;
		}

		if ( is_user_logged_in() && ( $this->permCoachOrAdmin() || get_current_user_id() === $order->getUserId() ) ) {
			return true;
		}

		if ( $paypalOrderId === null || $paypalOrderId === '' ) {
			return false;
		}

		return true;
	}

	/**
	 * @param \AllCoach\Application\Order\OrderView $view
	 * @return array<string, mixed>
	 */
	private function prepareView( \AllCoach\Application\Order\OrderView $view ): array {
		return [
			'id'                   => $view->id,
			'status'               => $view->status,
			'client_name'          => $view->clientName,
			'program_title'        => $view->programTitle,
			'payment_method'       => $view->paymentMethod,
			'payment_method_title' => $this->settingsManager->getPaymentMethodTitle( $view->paymentMethod ),
			'payment_status'       => $view->paymentStatus,
			'amount'               => $view->amount,
			'currency'             => $view->currency,
			'transaction_id'       => $view->transactionId,
			'paid_at'              => $view->paidAt,
		];
	}
}
