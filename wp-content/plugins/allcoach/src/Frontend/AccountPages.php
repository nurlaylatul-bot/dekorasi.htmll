<?php

declare(strict_types=1);

namespace AllCoach\Frontend;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Traits\Hooks;

class AccountPages {

	use Hooks;

	private const OPTION_KEY = '_allcoach_page_ids';

	private const PAGE_MAP = [
		'page_account'  => 'My Account Page',
		'page_checkout' => 'Checkout Page',
	];

	private const PAGE_DEFINITIONS = [
		'account'  => [
			'title'        => 'My Account',
			'content'      => '<!-- wp:shortcode -->[allcoach_account]<!-- /wp:shortcode -->',
			'settings_key' => 'page_account',
		],
		'checkout' => [
			'title'        => 'Checkout',
			'content'      => '<!-- wp:shortcode -->[allcoach_checkout]<!-- /wp:shortcode -->',
			'settings_key' => 'page_checkout',
		],
	];

	public function __construct(
		private readonly SettingsManager $settingsManager,
	) {}

	public function createPages(): void {
		$pageIds = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $pageIds ) ) {
			$pageIds = [];
		}

		/** @var array<string, array{title: string, content: string, settings_key: string}> $definitions */
		$definitions    = $this->applyFilters( 'allcoach:pages:definitions', self::PAGE_DEFINITIONS );
		$settingsUpdate = [];

		foreach ( $definitions as $name => $def ) {
			if ( ! empty( $pageIds[ $name ] ) && 'publish' === get_post_status( $pageIds[ $name ] ) ) {
				if ( isset( $def['settings_key'] ) ) {
					$current = (int) $this->settingsManager->get( $def['settings_key'] );
					if ( $current <= 0 ) {
						$settingsUpdate[ $def['settings_key'] ] = (int) $pageIds[ $name ];
					}
				}
				continue;
			}

			$id = wp_insert_post(
				[
					'post_title'     => $def['title'],
					'post_content'   => $def['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_author'    => 1,
					'comment_status' => 'closed',
				],
				true
			);

			if ( ! is_wp_error( $id ) ) {
				$pageIds[ $name ] = $id;

				if ( isset( $def['settings_key'] ) ) {
					$settingsUpdate[ $def['settings_key'] ] = $id;
				}
			}
		}

		update_option( self::OPTION_KEY, $pageIds );

		if ( ! empty( $settingsUpdate ) ) {
			$this->settingsManager->setMultiple( $settingsUpdate );
		}

		$this->doAction( 'allcoach:pages:created', $pageIds );
	}

	public function registerPostStates(): void {
		$this->addFilter( 'display_post_states', [ $this, 'addPostStates' ], 10, 2 );
	}

	/**
	 * @param string[] $states
	 * @param \WP_Post $post
	 * @return string[]
	 */
	public function addPostStates( array $states, \WP_Post $post ): array {
		foreach ( self::PAGE_MAP as $settingsKey => $label ) {
			$pageId = (int) $this->settingsManager->get( $settingsKey );
			if ( $pageId > 0 && $pageId === $post->ID ) {
				$states[ $settingsKey ] = $label;
			}
		}

		return $states;
	}

	public static function getPageId( string $name ): int {
		$settingsKey = 'page_' . $name;
		$settings    = new SettingsManager();
		$fromSetting = (int) $settings->get( $settingsKey );

		if ( $fromSetting > 0 && 'publish' === get_post_status( $fromSetting ) ) {
			return $fromSetting;
		}

		$pageIds = get_option( self::OPTION_KEY, [] );
		return (int) ( $pageIds[ $name ] ?? 0 );
	}

	public static function getAccountPageId(): int {
		return self::getPageId( 'account' );
	}

	public static function getCheckoutPageId(): int {
		return self::getPageId( 'checkout' );
	}

	public static function isAccountPage(): bool {
		$pageId = self::getAccountPageId();
		return $pageId > 0 && is_page( $pageId );
	}

	public static function isCheckoutPage(): bool {
		$pageId = self::getCheckoutPageId();
		return $pageId > 0 && is_page( $pageId );
	}
}
