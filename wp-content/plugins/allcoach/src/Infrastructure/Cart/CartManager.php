<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Cart;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Cart\Cart;
use AllCoach\Domain\Cart\CartItem;
use AllCoach\Domain\Cart\Store;
use AllCoach\Domain\Program\Program;
use AllCoach\Domain\Program\ProgramRepository;

final class CartManager {

	private const DEFAULT_CART_ID = 'default';

	private ?Cart $cart = null;

	public function __construct(
		private readonly ProgramRepository $programRepository,
	) {}

	public function getCart(): Cart {
		if ( $this->cart !== null ) {
			return $this->cart;
		}

		$this->cart = new Cart( self::DEFAULT_CART_ID, $this->resolveStore() );
		return $this->cart;
	}

	/**
	 * @throws ValidationException
	 */
	public function addProgram( int $programId ): Cart {
		$program = $this->programRepository->findById( $programId );

		if ( ! $program ) {
			throw ValidationException::withErrors(
				[ 'program' => esc_html__( 'Program not found.', 'allcoach' ) ]
			);
		}

		if ( ! $program->isActive() ) {
			throw ValidationException::withErrors(
				[ 'program' => esc_html__( 'This program is not currently available.', 'allcoach' ) ]
			);
		}

		$cart = $this->getCart();

		if ( $cart->hasProgramId( $programId ) ) {
			throw ValidationException::withErrors(
				[ 'program' => esc_html__( 'This program is already in your cart.', 'allcoach' ) ]
			);
		}

		$cart->add( $this->createItemFromProgram( $program ) );
		$cart->save();

		return $cart;
	}

	public function removeItem( string $itemId ): Cart {
		$cart = $this->getCart();
		$cart->remove( $itemId );
		$cart->save();

		return $cart;
	}

	public function clear(): Cart {
		$cart = $this->getCart();
		$cart->clear();

		return $cart;
	}

	/**
	 * @return array<string, string>
	 */
	public function validateForCheckout(): array {
		$errors = [];

		foreach ( $this->getCart()->all() as $item ) {
			$program = $this->programRepository->findById( $item->getProgramId() );

			if ( ! $program ) {
				$errors[ $item->getId() ] = sprintf(
					/* translators: %s: Program name */
					esc_html__( 'Program "%s" is no longer available.', 'allcoach' ),
					$item->getName()
				);
			} elseif ( ! $program->isActive() ) {
				$errors[ $item->getId() ] = sprintf(
					/* translators: %s: Program name */
					esc_html__( 'Program "%s" is no longer active.', 'allcoach' ),
					$item->getName()
				);
			}
		}

		return $errors;
	}

	private function createItemFromProgram( Program $program ): CartItem {
		return new CartItem(
			[
				'program_id' => $program->getId(),
				'name'       => $program->getTitle(),
				'price'      => $program->getPrice(),
				'quantity'   => 1,
				'tax'        => 0.00,
			]
		);
	}

	private function resolveStore(): Store {
		$userId = get_current_user_id();

		if ( $userId > 0 ) {
			return new UserMetaStore( $userId );
		}

		return new SessionStore();
	}
}
