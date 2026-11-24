<?php

declare(strict_types=1);

namespace AllCoach\Core\Features;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;

class FeatureManager {

	use Hooks;

	private array $features  = [];
	private array $overrides = [];

	public function __construct() {
		$this->loadFeatures();
		$this->loadOverrides();
	}

	public function isEnabled( string $feature ): bool {
		if ( isset( $this->overrides[ $feature ] ) ) {
			return $this->overrides[ $feature ];
		}

		if ( isset( $this->features[ $feature ] ) ) {
			$config = $this->features[ $feature ];

			if ( isset( $config['percentage'] ) ) {
				return $this->isInPercentageRollout( $feature, $config['percentage'] );
			}

			if ( isset( $config['users'] ) && is_user_logged_in() ) {
				return in_array( get_current_user_id(), $config['users'], true );
			}

			return $config['enabled'] ?? false;
		}

		return $this->applyFilters( "allcoach:feature:{$feature}", false );
	}

	public function enable( string $feature ): void {
		$this->features[ $feature ] = [ 'enabled' => true ];
		$this->persist();
	}

	public function disable( string $feature ): void {
		$this->features[ $feature ] = [ 'enabled' => false ];
		$this->persist();
	}

	public function override( string $feature, bool $enabled ): void {
		$this->overrides[ $feature ] = $enabled;
	}

	public function clearOverrides(): void {
		$this->overrides = [];
	}

	private function loadFeatures(): void {
		$this->features = get_option(
			'_allcoach_features',
			[
				'logging' => [ 'enabled' => true ],
				// TODO: add more features
			]
		);
	}

	private function loadOverrides(): void {
		if ( defined( 'ALLCOACH_FEATURE_OVERRIDES' ) ) {
			$this->overrides = constant( 'ALLCOACH_FEATURE_OVERRIDES' );
		}
	}

	private function persist(): void {
		update_option( '_allcoach_features', $this->features );
	}

	private function isInPercentageRollout( string $feature, int $percentage ): bool {
		$userId = get_current_user_id();
		$hash   = crc32( $feature . $userId );

		return ( $hash % 100 ) < $percentage;
	}

	public function all(): array {
		return $this->features;
	}
}
