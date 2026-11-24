<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\BulkActionsTrait;
use AllCoach\API\RestController;
use AllCoach\Application\Order\AbandonOrderService;
use AllCoach\Application\Order\CancelOrderService;
use AllCoach\Application\Order\ConfirmOrderPaymentService;
use AllCoach\Application\Order\ReactivateOrderService;
use AllCoach\Application\Order\CreateOrderDTO;
use AllCoach\Application\Order\CreateOrderService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Application\Order\OrderView;
use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Booking\SessionBookingQueryService;
use AllCoach\Core\Data\CurrencyData;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\PaymentMethod;
use AllCoach\Core\Database\Transaction;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Application\Client\ClientDTO;
use AllCoach\Application\Client\CreateClientService;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Infrastructure\Cart\CartManager;
use AllCoach\Infrastructure\Database\ConsentRepository;
use AllCoach\Infrastructure\Privacy\ConsentPolicy;
use AllCoach\Traits\Hooks;
use Carbon\CarbonImmutable;

class OrdersController extends RestController {

	use BulkActionsTrait;
	use Hooks;

	protected string $restBase = 'orders';

	public function __construct(
		private readonly CurrencyData $currencyData,
		private readonly SettingsManager $settingsManager,
		private readonly OrderQueryService $queryService,
		private readonly CreateOrderService $createService,
		private readonly ConfirmOrderPaymentService $confirmService,
		private readonly CancelOrderService $cancelService,
		private readonly AbandonOrderService $abandonService,
		private readonly ReactivateOrderService $reactivateService,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly SessionBookingQueryService $sessionQueryService,
		private readonly SessionRepository $sessionRepository,
		private readonly ActivityProgressQueryService $activityProgressService,
		private readonly ProgramRepository $programRepository,
		private readonly CartManager $cartManager,
		private readonly ClientRepository $clientRepository,
		private readonly CreateClientService $createClientService,
		private readonly ConsentPolicy $consentPolicy,
		private readonly ConsentRepository $consentRepository,
	) {}

	/**
	 * Block checkout when privacy consent is required but not given.
	 */
	private function consentGuard( \WP_REST_Request $request ): ?\WP_Error {
		if ( $this->consentPolicy->isRequired() && empty( $request->get_param( 'consent' ) ) ) {
			return $this->errorResponse(
				__( 'You must accept the privacy policy to continue.', 'allcoach' ),
				422
			);
		}

		return null;
	}

	/**
	 * Persist a consent record (user, timestamp, policy version) for auditing.
	 */
	private function recordConsent( int $userId ): void {
		if ( ! $this->consentPolicy->isRequired() || $userId <= 0 ) {
			return;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: null;

		$this->consentRepository->record(
			$userId,
			'checkout',
			$this->consentPolicy->version(),
			$this->consentPolicy->policyPageId() ?: null,
			$ip ?: null
		);
	}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/enrollments',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getEnrollments' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'client_id' => $this->argInteger(
							description: __( 'Filter by client (user) ID.', 'allcoach' ),
							min: 1,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getOrders' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => array_merge(
						$this->paginationArgs(),
						$this->contextArg(),
						[
							'client_id'  => $this->argInteger( description: __( 'Filter by client ID.', 'allcoach' ), min: 1 ),
							'program_id' => $this->argInteger( description: __( 'Filter by program ID.', 'allcoach' ), min: 1 ),
							'status'     => $this->argEnum(
								description: __( 'Filter by order status.', 'allcoach' ),
								values: [ 'pending', 'confirmed', 'cancelled' ],
							),
							'search'     => $this->argString(
								description: __( 'Search orders by ID, client, program, status, payment, or transaction ID.', 'allcoach' ),
							),
							'from_date'  => $this->argString(
								description: __( 'Filter orders created on or after this date (YYYY-MM-DD).', 'allcoach' ),
							),
							'to_date'    => $this->argString(
								description: __( 'Filter orders created on or before this date (YYYY-MM-DD).', 'allcoach' ),
							),
						]
					),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/checkout',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createClientOrder' ],
					'permission_callback' => fn() => is_user_logged_in(),
					'args'                => [
						'payment_method' => $this->argEnum(
							description: __( 'Payment method for this order.', 'allcoach' ),
							values: $this->getPaymentMethods(),
							default: $this->getPaymentMethods()[0] ?? 'offline',
						),
						'notes'          => $this->argString(
							description: __( 'Optional notes for this order.', 'allcoach' ),
							required: false,
						),
						'consent'        => [
							'description' => __( 'Whether the privacy policy was accepted.', 'allcoach' ),
							'type'        => 'boolean',
							'required'    => false,
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/guest-checkout',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createGuestOrder' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'first_name'     => $this->argString(
							description: __( 'Client first name.', 'allcoach' ),
							required: true,
							minLength: 1,
						),
						'last_name'      => $this->argString(
							description: __( 'Client last name.', 'allcoach' ),
							required: true,
							minLength: 1,
						),
						'email'          => [
							'description'       => __( 'Client email address.', 'allcoach' ),
							'type'              => 'string',
							'format'            => 'email',
							'required'          => true,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'phone'          => $this->argString(
							description: __( 'Client phone number.', 'allcoach' ),
							required: false,
						),
						'program_id'     => $this->argInteger(
							description: __( 'Program ID to enroll in.', 'allcoach' ),
							required: true,
							min: 1,
						),
						'payment_method' => $this->argEnum(
							description: __( 'Payment method for this order.', 'allcoach' ),
							values: $this->getPaymentMethods(),
							default: $this->getPaymentMethods()[0] ?? 'offline',
						),
						'notes'          => $this->argString(
							description: __( 'Optional notes for the order.', 'allcoach' ),
							required: false,
						),
						'consent'        => [
							'description' => __( 'Whether the privacy policy was accepted.', 'allcoach' ),
							'type'        => 'boolean',
							'required'    => false,
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createOrder' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->createArgs(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getOrder' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => array_merge( $this->idArg(), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteOrder' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_allcoach_orders' ),
					'args'                => $this->idArg(),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/confirm',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'confirmPayment' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => array_merge(
						$this->idArg(),
						[
							'transaction_id' => $this->argString(
								description: __( 'External transaction reference.', 'allcoach' ),
							),
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/cancel',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'cancelOrder' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/abandon',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'abandonOrder' ],
					'permission_callback' => fn() => is_user_logged_in(),
					'args'                => $this->idArg(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/reactivate',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reactivateOrder' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
			]
		);

		$this->registerBulkRoute();
	}

	protected function supportedBulkActions(): array {
		return [ 'delete', 'confirm', 'cancel', 'reactivate' ];
	}

	protected function bulkPermissionCallback(): bool {
		return $this->permCoachOrAdmin();
	}

	protected function executeBulkAction( string $action, int $id ): array {
		try {
			match ( $action ) {
				'delete' => ( function () use ( $id ) {
					$view = $this->queryService->findById( $id );
					if ( ! $view ) {
						throw new \RuntimeException( esc_html__( 'Order not found.', 'allcoach' ) );
					}
					Transaction::run(
						function () use ( $id, $view ) {
							$this->enrollmentRepository->delete( $view->enrollmentId );
							$this->orderItemRepository->deleteByOrderId( $id );
							$this->orderRepository->delete( $id );
						}
					);
				} )(),
				'confirm'    => $this->confirmService->execute( $id ),
				'cancel'     => $this->cancelService->execute( $id ),
				'reactivate' => $this->reactivateService->execute( $id ),
			};

			return [
				'id'      => $id,
				'success' => true,
			];
		} catch ( \Throwable $e ) {
			return [
				'id'      => $id,
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/** @return list<string> */
	private function getPaymentMethods(): array {
		$methods = $this->settingsManager->getEnabledPaymentMethods();
		return $methods !== [] ? $methods : PaymentMethod::all();
	}

	public function getEnrollments( \WP_REST_Request $request ): \WP_REST_Response {
		$clientId    = $request->get_param( 'client_id' ) ? (int) $request->get_param( 'client_id' ) : null;
		$enrollments = $clientId !== null
			? $this->enrollmentRepository->findByUser( $clientId )
			: $this->enrollmentRepository->findAll();

		$enrollments = array_filter( $enrollments, fn( Enrollment $e ) => $e->isActive() );

		$items = array_map(
			function ( Enrollment $enrollment ) {
				$program = $this->programRepository->findById( $enrollment->getProgramId() );
				return [
					'id'          => $enrollment->getId(),
					'program_id'  => $enrollment->getProgramId(),
					'program'     => $program ? [
						'id'    => $program->getId(),
						'title' => $program->getTitle(),
					] : null,
					'status'      => $enrollment->getStatus()->getValue(),
					'enrolled_at' => $enrollment->getEnrolledAt()->format( 'c' ),
				];
			},
			$enrollments
		);

		return $this->successResponse( [ 'enrollments' => array_values( $items ) ] );
	}

	public function getOrders( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$context = $this->getEffectiveContext( $request, 'manage_allcoach_orders' );
		$schema  = $this->getItemSchema();

		$clientId  = $request->get_param( 'client_id' ) ? (int) $request->get_param( 'client_id' ) : null;
		$programId = $request->get_param( 'program_id' ) ? (int) $request->get_param( 'program_id' ) : null;
		$status    = $request->get_param( 'status' );
		$search    = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$fromDate  = sanitize_text_field( (string) ( $request->get_param( 'from_date' ) ?? '' ) );
		$toDate    = sanitize_text_field( (string) ( $request->get_param( 'to_date' ) ?? '' ) );

		$filter = new OrderFilter(
			clientId: $clientId,
			programId: $programId,
			status: $status ? (string) $status : null,
			fromDate: $fromDate !== '' ? $fromDate : null,
			toDate: $toDate !== '' ? $toDate : null,
			excludeDemo: true,
			search: $search !== '' ? $search : null,
		);

		$orders = $this->queryService->findWithFilter( $filter, $page, $perPage );

		$items = array_map(
			fn( OrderView $v ) => $this->filterDataByContext( $this->prepareOrderView( $v ), $schema, $context ),
			$orders
		);

		return $this->successResponse(
			[
				'orders'   => $items,
				'total'    => $this->queryService->getTotalCount(),
				'page'     => $page,
				'per_page' => $perPage,
			]
		);
	}

	public function getOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$view = $this->queryService->findById( $id );

		if ( ! $view ) {
			return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
		}

		$context = $this->getEffectiveContext( $request, 'manage_allcoach_orders' );
		$data    = $this->filterDataByContext( $this->prepareOrderView( $view ), $this->getItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function createOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_params();

		$validation = $this->validateRequired( $data, [ 'client_id', 'program_id' ] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$dto  = CreateOrderDTO::fromArray( $data );
			$view = $this->createService->execute( $dto );

			return $this->successResponse( $this->prepareOrderView( $view ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function createClientOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$clientId = get_current_user_id();
		if ( ! $clientId ) {
			return $this->forbiddenResponse();
		}

		if ( $consentError = $this->consentGuard( $request ) ) {
			return $consentError;
		}

		$this->recordConsent( $clientId );

		if ( ! $this->isClient() ) {
			$wpUser = wp_get_current_user();
			try {
				$this->createClientService->execute(
					ClientDTO::fromArray(
						[
							'first_name' => $wpUser->first_name ?: $wpUser->display_name,
							'last_name'  => $wpUser->last_name,
							'email'      => $wpUser->user_email,
							'status'     => 'active',
						]
					)
				);
			} catch ( \Throwable $e ) {
				return $this->errorResponse(
					__( 'Could not register you as a client. Please contact the site administrator.', 'allcoach' ),
					422
				);
			}
		}

		$cart = $this->cartManager->getCart();

		if ( $cart->isEmpty() ) {
			return $this->errorResponse(
				__( 'Your cart is empty. Please add a program before checking out.', 'allcoach' ),
				422
			);
		}

		// Validate all items before processing any — fail fast, keep cart intact.
		$validationErrors = $this->cartManager->validateForCheckout();
		if ( ! empty( $validationErrors ) ) {
			return $this->errorResponse(
				implode( ' ', $validationErrors ),
				422
			);
		}

		if ( empty( $this->settingsManager->getEnabledPaymentMethods() ) ) {
			foreach ( $cart->all() as $item ) {
				$p = $this->programRepository->findById( $item->getProgramId() );
				if ( $p && ! $p->isFree() ) {
					return $this->errorResponse(
						__( 'No payment methods are enabled. Please contact the site administrator.', 'allcoach' ),
						422
					);
				}
			}
		}

		$paymentMethod = (string) ( $request->get_param( 'payment_method' ) ?? ( $this->getPaymentMethods()[0] ?? 'offline' ) );
		$notes         = $request->get_param( 'notes' ) ?? null;
		$orders        = [];
		/**
		 * Lets addons block checkout with validation errors before any order is
		 * created (e.g. Pro Contracts requiring a signed agreement). Return one
		 * or more human-readable messages to abort with a 422.
		 *
		 * @param string[]                 $errors   Accumulated blocking messages.
		 * @param \AllCoach\Domain\Cart\Cart $cart     The cart being checked out.
		 * @param int                      $clientId Current client user ID.
		 */
		$guardErrors = $this->applyFilters( 'allcoach:checkout:guard', [], $cart, $clientId );
		if ( ! empty( $guardErrors ) ) {
			return $this->errorResponse( implode( ' ', array_map( 'strval', $guardErrors ) ), 422 );
		}

		$notes  = $request->get_param( 'notes' ) ?? null;
		$orders = [];

		foreach ( $cart->all() as $item ) {
			$program = $this->programRepository->findById( $item->getProgramId() );

			$amount  = $program->getPrice();
			$payload = [
				'client_id'      => $clientId,
				'program_id'     => $item->getProgramId(),
				'payment_method' => $amount <= 0 ? 'offline' : $paymentMethod,
				'amount'         => $amount,
				'currency'       => (string) $this->settingsManager->get( 'currency' ),
				'notes'          => $notes,
				'status'         => $amount <= 0 ? 'confirmed' : 'pending',
				'consent'        => ! empty( $request->get_param( 'consent' ) ),
			];

			/** @var array<string, mixed> $payload */
			$payload = $this->applyFilters( 'allcoach:order:checkout_payload', $payload );

			try {
				$dto  = CreateOrderDTO::fromArray( $payload );
				$view = $this->createService->execute( $dto );

				$this->doAction( 'allcoach:order:checkout_created', $view );

				$orders[] = $this->prepareOrderView( $view );
			} catch ( \Throwable $e ) {
				// Validation passed but creation failed (e.g. duplicate enrollment).
				// Clear cart and return what we have.
				$cart->clear();

				if ( empty( $orders ) ) {
					return $this->exceptionToResponse( $e );
				}

				return $this->successResponse(
					[
						'orders' => $orders,
						'errors' => [ $item->getName() . ': ' . $e->getMessage() ],
					],
					201
				);
			}
		}

		$cart->clear();

		return $this->successResponse(
			[
				'orders' => $orders,
				'errors' => [],
			],
			201
		);
	}

	public function createGuestOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( $consentError = $this->consentGuard( $request ) ) {
			return $consentError;
		}

		$firstName = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
		$lastName  = sanitize_text_field( (string) $request->get_param( 'last_name' ) );
		$email     = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone     = sanitize_text_field( (string) ( $request->get_param( 'phone' ) ?? '' ) );
		$programId = (int) $request->get_param( 'program_id' );
		$notes     = sanitize_text_field( (string) ( $request->get_param( 'notes' ) ?? '' ) );

		if ( ! $firstName || ! $lastName ) {
			return $this->errorResponse( __( 'First name and last name are required.', 'allcoach' ), 422 );
		}

		if ( ! is_email( $email ) ) {
			return $this->errorResponse( __( 'A valid email address is required.', 'allcoach' ), 422 );
		}

		$program = $this->programRepository->findById( $programId );
		if ( ! $program ) {
			return $this->errorResponse( __( 'Program not found.', 'allcoach' ), 404 );
		}

		if ( ! $program->isActive() ) {
			return $this->errorResponse( __( 'This program is not currently available.', 'allcoach' ), 422 );
		}

		if ( ! $program->isFree() && empty( $this->settingsManager->getEnabledPaymentMethods() ) ) {
			return $this->errorResponse(
				__( 'No payment methods are enabled. Please contact the site administrator.', 'allcoach' ),
				422
			);
		}

		/**
		 * Lets addons block guest checkout before any account or order is created
		 * (e.g. Pro Contracts requiring a signed agreement, which guests cannot
		 * sign). Return one or more human-readable messages to abort with a 422.
		 *
		 * @param string[]                         $errors  Accumulated blocking messages.
		 * @param \AllCoach\Domain\Program\Program $program The program being purchased.
		 * @param string                           $email   The guest's email address.
		 */
		$guardErrors = $this->applyFilters( 'allcoach:checkout:guest_guard', [], $program, $email );
		if ( ! empty( $guardErrors ) ) {
			return $this->errorResponse( implode( ' ', array_map( 'strval', $guardErrors ) ), 422 );
		}

		try {
			$client = $this->createClientService->execute(
				ClientDTO::fromArray(
					[
						'first_name' => $firstName,
						'last_name'  => $lastName,
						'email'      => $email,
						'phone'      => $phone,
						'timezone'   => wp_timezone_string(),
						'status'     => 'active',
						'notes'      => '',
					]
				)
			);
		} catch ( NotFoundException ) {
			return $this->errorResponse(
				__( 'An account with this email already exists. Please log in to continue and complete your payment.', 'allcoach' ),
				422
			);
		} catch ( \Throwable ) {
			return $this->errorResponse(
				__( 'Failed to create your account. Please try again.', 'allcoach' ),
				422
			);
		}

		$clientId = (int) $client->getId();

		$this->recordConsent( $clientId );

		try {
			$guestAmount   = $program->getPrice();
			$paymentMethod = (string) ( $request->get_param( 'payment_method' ) ?? ( $this->getPaymentMethods()[0] ?? 'offline' ) );
			$dto           = CreateOrderDTO::fromArray(
				[
					'client_id'      => $clientId,
					'program_id'     => $programId,
					'payment_method' => $guestAmount <= 0 ? 'offline' : $paymentMethod,
					'amount'         => $guestAmount,
					'currency'       => (string) $this->settingsManager->get( 'currency' ),
					'notes'          => $notes ?: null,
					'status'         => $guestAmount <= 0 ? 'confirmed' : 'pending',
					'consent'        => ! empty( $request->get_param( 'consent' ) ),
				]
			);
			$view          = $this->createService->execute( $dto );

			$orderData                 = $this->prepareOrderView( $view );
			$orderData['client_email'] = $email;

			return $this->successResponse(
				[
					'orders' => [ $orderData ],
					'errors' => [],
				],
				201
			);
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function confirmPayment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id            = (int) $request->get_param( 'id' );
		$transactionId = $request->get_param( 'transaction_id' );

		try {
			$order = $this->orderRepository->findById( $id );
			if ( ! $order ) {
				return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
			}

			$transactionId = $this->applyFilters(
				'allcoach:order:confirm_transaction_id',
				$transactionId,
				$order
			);

			$view = $this->confirmService->execute( $id, $transactionId );

			return $this->successResponse( $this->prepareOrderView( $view ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function cancelOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		try {
			$view = $this->cancelService->execute( $id );

			return $this->successResponse( $this->prepareOrderView( $view ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function abandonOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		$order = $this->orderRepository->findById( $id );
		if ( ! $order || $order->getUserId() !== get_current_user_id() ) {
			return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
		}

		try {
			$this->abandonService->execute( $id );

			return $this->successResponse( [] );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function reactivateOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		try {
			$view = $this->reactivateService->execute( $id );

			return $this->successResponse( $this->prepareOrderView( $view ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteOrder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$view = $this->queryService->findById( $id );

		if ( ! $view ) {
			return $this->notFoundResponse( __( 'Order', 'allcoach' ) );
		}

		try {
			Transaction::run(
				function () use ( $id, $view ) {
					$this->enrollmentRepository->delete( $view->enrollmentId );
					$this->orderItemRepository->deleteByOrderId( $id );
					$this->orderRepository->delete( $id );
				}
			);
		} catch ( \Throwable ) {
			return $this->errorResponse( __( 'Failed to delete order.', 'allcoach' ), 500 );
		}

		return $this->successResponse(
			[
				'deleted' => true,
				'id'      => $id,
			]
		);
	}

	private function prepareOrderView( OrderView $view ): array {
		$session          = $this->sessionRepository->findOneByProgramId( $view->programId );
		$totalSessions    = $session ? $session->getRepetitionCount() : 0;
		$sessionProgress  = $this->sessionQueryService->getProgress( $view->enrollmentId, $totalSessions );
		$activityProgress = $this->activityProgressService->getProgress( $view->enrollmentId, $view->programId );

		return [
			'id'                    => $view->id,
			'enrollment_id'         => $view->enrollmentId,
			'client_id'             => $view->clientId,
			'program_id'            => $view->programId,
			'client_name'           => $view->clientName,
			'program_title'         => $view->programTitle,
			'status'                => $view->status,
			'payment_method'        => $view->paymentMethod,
			'payment_method_title'  => $this->settingsManager->getPaymentMethodTitle( $view->paymentMethod ),
			'payment_status'        => $view->paymentStatus,
			'amount'                => $view->amount,
			'currency'              => $view->currency,
			'transaction_id'        => $view->transactionId,
			'notes'                 => $view->notes,
			'session_progress'      => $sessionProgress,
			'activity_progress'     => $activityProgress,
			'paid_at'               => $view->paidAt ? CarbonImmutable::parse( $view->paidAt )->format( 'c' ) : null,
			'created_at'            => CarbonImmutable::parse( $view->createdAt )->format( 'c' ),
			'updated_at'            => $view->updatedAt ? CarbonImmutable::parse( $view->updatedAt )->format( 'c' ) : null,
		];
	}

	private function createArgs(): array {
		return [
			'client_id'      => $this->argInteger(
				description: __( 'Client user ID.', 'allcoach' ),
				required: true,
				min: 1,
			),
			'program_id'     => $this->argInteger(
				description: __( 'Program ID for the order.', 'allcoach' ),
				required: true,
				min: 1,
			),
			'payment_method' => $this->argEnum(
				description: __( 'Payment method.', 'allcoach' ),
				values: $this->getPaymentMethods(),
				default: $this->getPaymentMethods()[0] ?? 'offline',
			),
			'amount'         => $this->argNumber(
				description: __( 'Payment amount.', 'allcoach' ),
				min: 0.0,
				default: 0.0,
			),
			'currency'       => $this->argEnum(
				description: __( 'ISO 4217 currency code.', 'allcoach' ),
				values: $this->currencyData->getCodes(),
				default: 'USD',
			),
			'notes'          => $this->argString(
				description: __( 'Admin notes for this order.', 'allcoach' ),
			),
			'status'         => $this->argEnum(
				description: __( 'Initial order status.', 'allcoach' ),
				values: [ 'pending', 'confirmed' ],
				default: 'pending',
			),
		];
	}

	public function getItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_order',
			'type'       => 'object',
			'properties' => [
				'id'                => [
					'description' => __( 'Order ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'enrollment_id'     => [
					'description' => __( 'Enrollment ID for this order.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'client_id'         => [
					'description' => __( 'Client user ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'program_id'        => [
					'description' => __( 'Program ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'client_name'       => [
					'description' => __( 'Client display name.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'program_title'     => [
					'description' => __( 'Program title.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'status'            => [
					'description' => __( 'Order status.', 'allcoach' ),
					'type'        => 'string',
					'enum'        => [ 'pending', 'confirmed', 'cancelled' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'payment_method'    => [
					'description' => __( 'Payment method used.', 'allcoach' ),
					'type'        => 'string',
					'enum'        => $this->getPaymentMethods(),
					'context'     => [ 'view', 'edit' ],
				],
				'payment_status'    => [
					'description' => __( 'Payment status.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'amount'            => [
					'description' => __( 'Payment amount.', 'allcoach' ),
					'type'        => 'number',
					'minimum'     => 0,
					'context'     => [ 'view', 'edit' ],
				],
				'currency'          => [
					'description' => __( 'Currency code (ISO 4217).', 'allcoach' ),
					'type'        => 'string',
					'enum'        => $this->currencyData->getCodes(),
					'context'     => [ 'view', 'edit' ],
				],
				'transaction_id'    => [
					'description' => __( 'External transaction reference.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
				'notes'             => [
					'description' => __( 'Admin notes.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
				],
				'session_progress'  => [
					'description' => __( 'Session progress for this enrollment.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'properties'  => [
						'scheduled' => [
							'description' => __( 'Number of sessions scheduled (not cancelled).', 'allcoach' ),
							'type'        => 'integer',
						],
						'completed' => [
							'description' => __( 'Number of sessions completed.', 'allcoach' ),
							'type'        => 'integer',
						],
						'total'     => [
							'description' => __( 'Total sessions required for the program.', 'allcoach' ),
							'type'        => 'integer',
						],
						'remaining' => [
							'description' => __( 'Sessions yet to be scheduled.', 'allcoach' ),
							'type'        => 'integer',
						],
					],
				],
				'activity_progress' => [
					'description' => __( 'Activity completion progress for this enrollment.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'properties'  => [
						'completed'              => [
							'description' => __( 'Number of activities completed.', 'allcoach' ),
							'type'        => 'integer',
						],
						'total'                  => [
							'description' => __( 'Total activities in the program.', 'allcoach' ),
							'type'        => 'integer',
						],
						'percentage'             => [
							'description' => __( 'Completion percentage.', 'allcoach' ),
							'type'        => 'number',
						],
						'completed_activity_ids' => [
							'description' => __( 'IDs of completed activities.', 'allcoach' ),
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
						],
					],
				],
				'paid_at'           => [
					'description' => __( 'Payment confirmation date (ISO 8601).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'created_at'        => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'updated_at'        => [
					'description' => __( 'Last update date (ISO 8601).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}
}
