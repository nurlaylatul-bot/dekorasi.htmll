<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Cart\Cart;
use AllCoach\Domain\Cart\CartItem;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Infrastructure\Cart\CartManager;

class CartController extends RestController {

	protected string $restBase = 'cart';

	public function __construct(
		private readonly CartManager $cartManager,
		private readonly ProgramRepository $programRepository,
		private readonly SettingsManager $settingsManager,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCart' ],
					'permission_callback' => fn() => is_user_logged_in(),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'clearCart' ],
					'permission_callback' => fn() => is_user_logged_in(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/items',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'addItem' ],
					'permission_callback' => fn() => is_user_logged_in(),
					'args'                => [
						'program_id' => $this->argInteger(
							description: __( 'Program to add to cart.', 'allcoach' ),
							required: true,
							min: 1,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/items/(?P<item_id>[a-f0-9]{64})',
			[
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'removeItem' ],
					'permission_callback' => fn() => is_user_logged_in(),
					'args'                => [
						'item_id' => [
							'description'       => __( 'Cart item ID.', 'allcoach' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	public function getCart(): \WP_REST_Response {
		return $this->successResponse(
			$this->formatCart( $this->cartManager->getCart() )
		);
	}

	public function addItem( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$cart = $this->cartManager->addProgram(
				(int) $request->get_param( 'program_id' )
			);
			return $this->successResponse( $this->formatCart( $cart ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function removeItem( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$itemId = (string) $request->get_param( 'item_id' );
		$cart   = $this->cartManager->getCart();

		if ( ! $cart->has( $itemId ) ) {
			return $this->notFoundResponse( __( 'Cart item', 'allcoach' ) );
		}

		$this->cartManager->removeItem( $itemId );

		return $this->successResponse( $this->formatCart( $cart ) );
	}

	public function clearCart(): \WP_REST_Response {
		return $this->successResponse(
			$this->formatCart( $this->cartManager->clear() )
		);
	}

	private function formatCart( Cart $cart ): array {
		$currency = (string) $this->settingsManager->get( 'currency' );
		$items    = array_map(
			function ( CartItem $item ) use ( $currency ) {
				$program = $this->programRepository->findById( $item->getProgramId() );

				return [
					'id'         => $item->getId(),
					'program_id' => $item->getProgramId(),
					'name'       => $item->getName(),
					'price'      => $item->getPrice(),
					'tax'        => $item->getTax(),
					'quantity'   => $item->getQuantity(),
					'total'      => $item->getTotalPrice(),
					'program'    => $program ? [
						'id'             => $program->getId(),
						'title'          => $program->getTitle(),
						'price'          => $program->getPrice(),
						'currency'       => $currency,
						'duration_value' => $program->getDuration()->getValue(),
						'duration_unit'  => $program->getDuration()->getUnit(),
						'coach_id'       => $program->getCoachId(),
					] : null,
				];
			},
			$cart->all()
		);

		return [
			'items'    => $items,
			'subtotal' => $cart->subtotal(),
			'tax'      => $cart->tax(),
			'total'    => $cart->total(),
			'count'    => $cart->count(),
			'currency' => $currency,
			'is_empty' => $cart->isEmpty(),
		];
	}
}
