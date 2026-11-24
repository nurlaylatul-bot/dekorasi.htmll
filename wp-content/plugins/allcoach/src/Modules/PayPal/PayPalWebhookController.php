<?php

declare(strict_types=1);

namespace AllCoach\Modules\PayPal;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Order\CancelOrderService;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Modules\PayPal\PayPalPaymentGateway;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class PayPalWebhookController extends RestController {

	use Hooks;

	protected string $restBase = 'payment/paypal/webhook';

	public function __construct(
		private readonly PayPalPaymentGateway $paypalGateway,
		private readonly OrderRepository $orderRepository,
		private readonly CancelOrderService $cancelService,
		private readonly Logger $logger,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handleWebhook' ],
				'permission_callback' => '__return_true',
				'args'                => [],
			]
		);
	}

	public function handleWebhook( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$rawBody = $request->get_body();
		$headers = $this->normalizeHeaders( $request );

		if ( ! $this->paypalGateway->verifyWebhookSignature( $headers, $rawBody ) ) {
			$this->logger->warning( 'PayPal webhook signature verification failed', [ 'headers' => $headers ] );
			return new \WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
		}

		$event = json_decode( $rawBody, true, 512, JSON_BIGINT_AS_STRING );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $event ) ) {
			$this->logger->warning( 'PayPal webhook: invalid JSON body', [ 'json_error' => json_last_error_msg() ] );
			return new \WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
		}

		$eventType = $event['event_type'] ?? '';

		$this->doAction( 'allcoach:payment:paypal:webhook_event', $eventType, $event );

		$handler = $this->resolveHandler( $eventType );
		if ( $handler !== null ) {
			return $handler( $event );
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * @return callable(array):\WP_REST_Response|null
	 */
	private function resolveHandler( string $eventType ): ?callable {
		$handlers = [
			'CHECKOUT.ORDER.APPROVED'            => fn( array $event ) => $this->onOrderApproved( $event ),
			'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => fn( array $event ) => $this->onCaptureFailed( $event ),
			'PAYMENT.AUTHORIZATION.CREATED'      => fn( array $event ) => $this->onAuthorizationCreated( $event ),
			'PAYMENT.AUTHORIZATION.VOIDED'       => fn( array $event ) => $this->onAuthorizationVoided( $event ),
			'PAYMENT.CAPTURE.COMPLETED'          => fn( array $event ) => $this->onCaptureCompleted( $event ),
			'PAYMENT.CAPTURE.DECLINED'           => fn( array $event ) => $this->onCaptureFailed( $event ),
			'PAYMENT.CAPTURE.DENIED'             => fn( array $event ) => $this->onCaptureFailed( $event ),
			'PAYMENT.CAPTURE.REFUNDED'           => fn( array $event ) => $this->onCaptureRefunded( $event ),
			'PAYMENT.CAPTURE.REVERSED'           => fn( array $event ) => $this->onCaptureRefunded( $event ),
		];

		/**
		 * @param array<string, callable(array):\WP_REST_Response> $handlers
		 * @param string                                            $eventType
		 */
		$handlers = $this->applyFilters( 'allcoach:payment:paypal:webhook_handlers', $handlers, $eventType );

		return $handlers[ $eventType ] ?? null;
	}

	private function onCaptureCompleted( array $event ): \WP_REST_Response {
		$resourceData = $event['resource'] ?? [];
		$captureId    = $resourceData['id'] ?? null;
		$orderId      = $this->extractOrderId( $resourceData );

		if ( $orderId === null ) {
			$this->logger->warning( 'PayPal webhook: missing custom_id', [ 'resource' => $resourceData ] );
			return new \WP_REST_Response( [ 'error' => 'Missing order reference' ], 422 );
		}

		$order = $this->orderRepository->findById( $orderId );
		if ( $order === null ) {
			$this->logger->warning( 'PayPal webhook: order not found', [ 'order_id' => $orderId ] );
			return new \WP_REST_Response( [ 'error' => 'Order not found' ], 422 );
		}

		$order->markPaid( $captureId );
		$this->orderRepository->save( $order );

		$this->logger->info(
			'PayPal payment captured',
			[
				'order_id'   => $orderId,
				'capture_id' => $captureId,
			]
		);

		$this->doAction( 'allcoach:payment:paypal:captured', $order, $captureId );

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private function onCaptureFailed( array $event ): \WP_REST_Response {
		$resourceData = $event['resource'] ?? [];
		$orderId      = $this->extractOrderId( $resourceData );

		if ( $orderId === null ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$order = $this->orderRepository->findById( $orderId );
		if ( $order !== null && ! $order->getStatus()->isPaid() ) {
			$order->markFailed();
			$this->orderRepository->save( $order );
			$this->logger->info(
				'PayPal payment failed before capture',
				[
					'order_id' => $orderId,
					'event'    => $event['event_type'],
				]
			);
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * PayPal sends AUTHORIZATION.VOIDED both for a manual void and when an
	 * authorization lapses past its 30-day validity — there is no separate
	 * "expired" event.
	 */
	private function onAuthorizationVoided( array $event ): \WP_REST_Response {
		$resourceData = $event['resource'] ?? [];
		$orderId      = $this->extractOrderId( $resourceData );

		if ( $orderId === null ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$order = $this->orderRepository->findById( $orderId );
		if ( $order !== null && ! $order->getStatus()->isPaid() ) {
			$order->markFailed();
			$this->orderRepository->save( $order );
			$this->logger->info(
				'PayPal authorization voided',
				[
					'order_id' => $orderId,
					'event'    => $event['event_type'],
				]
			);
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * Money returned after a successful capture. Unlike a pre-capture failure,
	 * the client already holds an active enrollment, so the enrollment has to be
	 * cancelled too — CancelOrderService does that and marks the order refunded.
	 */
	private function onCaptureRefunded( array $event ): \WP_REST_Response {
		$resourceData = $event['resource'] ?? [];
		$orderId      = $this->extractOrderId( $resourceData );

		if ( $orderId === null ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		try {
			$this->cancelService->execute( $orderId );
			$this->logger->info(
				'PayPal capture refunded/reversed',
				[
					'order_id' => $orderId,
					'event'    => $event['event_type'],
				]
			);
		} catch ( \Throwable $e ) {
			$this->logger->warning(
				'PayPal refund webhook could not cancel the order',
				[
					'order_id' => $orderId,
					'event'    => $event['event_type'],
					'error'    => $e->getMessage(),
				]
			);
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private function onAuthorizationCreated( array $event ): \WP_REST_Response {
		$resourceData    = $event['resource'] ?? [];
		$authorizationId = $resourceData['id'] ?? null;
		$orderId         = $this->extractOrderId( $resourceData );

		if ( $orderId === null || $authorizationId === null ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$order = $this->orderRepository->findById( $orderId );
		if ( $order === null || ! $order->getStatus()->isPending() ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$expectedReference = 'AUTH:' . $authorizationId;
		if ( $order->getPaymentReference() === $expectedReference ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$order->setPaymentReference( $expectedReference );
		$this->orderRepository->save( $order );

		$this->logger->info(
			'PayPal authorization created (webhook safety net)',
			[
				'order_id'         => $orderId,
				'authorization_id' => $authorizationId,
			]
		);

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private function onOrderApproved( array $event ): \WP_REST_Response {
		$resourceData = $event['resource'] ?? [];
		$orderId      = $this->extractOrderId( $resourceData );

		$this->logger->info(
			'PayPal checkout order approved',
			[
				'order_id'        => $orderId,
				'paypal_order_id' => $resourceData['id'] ?? null,
			]
		);

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private function extractOrderId( array $resourceData ): ?int {
		$raw = $resourceData['custom_id']
			?? $resourceData['purchase_units'][0]['custom_id']
			?? null;

		if ( $raw === null || $raw === '' ) {
			return null;
		}

		$id = (int) $raw;
		return $id > 0 ? $id : null;
	}

	/**
	 * @return array<string, string>
	 */
	private function normalizeHeaders( \WP_REST_Request $request ): array {
		$raw        = $request->get_headers();
		$normalized = [];

		foreach ( $raw as $key => $values ) {
			$canonicalKey                = strtoupper( str_replace( '_', '-', $key ) );
			$normalized[ $canonicalKey ] = is_array( $values ) ? reset( $values ) : $values;
		}

		return $normalized;
	}
}
