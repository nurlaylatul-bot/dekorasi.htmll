<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Privacy;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Traits\Hooks;

/**
 * Resolves GDPR consent configuration (whether consent is required, the policy
 * version stamped on records, and the privacy-policy link) for the checkout and
 * booking flows.
 *
 * The privacy policy link points at WordPress's designated privacy policy page
 * (Settings > Privacy), the standard place site owners set it.
 */
class ConsentPolicy {

	use Hooks;

	public function __construct( private SettingsManager $settings ) {}

	public function isRequired(): bool {
		return (bool) $this->settings->get( 'consent_required' );
	}

	public function version(): string {
		return (string) $this->settings->get( 'privacy_policy_version' );
	}

	/**
	 * The WordPress privacy policy page id (0 when none is configured).
	 */
	public function policyPageId(): int {
		return (int) get_option( 'wp_page_for_privacy_policy', 0 );
	}

	public function policyUrl(): string {
		/** Filters the privacy-policy URL used by the consent checkbox. */
		return (string) $this->applyFilters( 'allcoach:consent:policy_url', (string) get_privacy_policy_url() );
	}

	/**
	 * The raw, unescaped consent label with the `{privacy_policy}` token.
	 */
	public function rawText(): string {
		$text = (string) $this->settings->get( 'consent_text' );
		return $text !== '' ? $text : __( 'I agree to the {privacy_policy}.', 'allcoach' );
	}

	/**
	 * The consent label as safe HTML, with `{privacy_policy}` replaced by a link
	 * to the privacy policy page (plain text when no page is configured).
	 */
	public function label(): string {
		$linkText = __( 'privacy policy', 'allcoach' );
		$url      = $this->policyUrl();

		$replacement = $url !== ''
			? sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $url ),
				esc_html( $linkText )
			)
			: esc_html( $linkText );

		// Escape the admin-entered text first, then inject the (safe) link markup.
		return str_replace( '{privacy_policy}', $replacement, esc_html( $this->rawText() ) );
	}

	/**
	 * Config payload for localizing to the frontend scripts.
	 *
	 * @return array{required: bool, label: string, version: string, errorMessage: string}
	 */
	public function toScriptData(): array {
		return [
			'required'     => $this->isRequired(),
			'label'        => $this->label(),
			'version'      => $this->version(),
			'errorMessage' => __( 'Please accept the privacy policy to continue.', 'allcoach' ),
		];
	}
}
