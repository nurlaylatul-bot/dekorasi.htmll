<?php

declare(strict_types=1);

namespace AllCoach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;
use AllCoach\Core\Settings\SettingsManager;

class Formbricks {


	private const ENVIRONMENT_ID = 'cmpoz59ty0d2qz001ug5bffs2';

	public function __construct( private readonly SettingsManager $settings ) {
		add_action( 'admin_enqueue_scripts', [ $this, 'declareInternalPage' ] );
		add_filter( 'themegrill-sdk/survey/' . $this->productSlug(), [ $this, 'configureSurvey' ], 10, 2 );
	}

	public function declareInternalPage( string $hook ): void {
		if ( ! str_contains( $hook, 'allcoach' ) ) {
			return;
		}

		do_action( 'themegrill_internal_page', $this->productSlug(), $hook );
	}

	public function configureSurvey( array $data, string $pageSlug ): array {
		if ( ! $this->settings->get( 'send_anonymous_usage_data' ) ) {
			return [];
		}

		return [
			'environmentId' => self::ENVIRONMENT_ID,
			'attributes'    => [
				'product'             => 'allcoach',
				'is_premium'          => (bool) apply_filters( 'allcoach_is_premium', false ),
				'plugin_version'      => Constants::VERSION,
				'install_days_number' => $this->installDays(),
			],
		];
	}

	private function productSlug(): string {
		return dirname( Constants::pluginBasename() );
	}

	private function installDays(): int {
		$installedAt = (int) get_option( $this->productSlug() . '_install', time() );

		return (int) floor( ( time() - $installedAt ) / DAY_IN_SECONDS );
	}
}
