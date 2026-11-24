<?php

declare(strict_types=1);

namespace AllCoach\Core\Settings;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;
use AllCoach\Traits\Hooks;

class SettingsManager {

	use Hooks;

	private const OPTION_KEY = '_allcoach_settings';

	private const HTML_BODY_KEYS = [
		'email_tpl_new_booking_confirmation_body',
		'email_tpl_new_booking_alert_body',
		'email_tpl_cancellation_notice_body',
		'email_tpl_24h_reminder_body',
		'email_tpl_order_confirmed_body',
		'email_tpl_order_cancelled_body',
		'email_tpl_welcome_client_body',
		'email_tpl_session_booking_confirmation_body',
		'email_tpl_session_completed_follow_up_body',
		'email_tpl_session_cancelled_alert_body',
	];

	private const HEX_COLOR_KEYS = [
		'primary_color',
		'button_color',
		'button_hover_color',
	];

	private const DEFAULTS = [
		// Styling
		'primary_color'                                  => '#0d9488',
		'button_color'                                   => '#0d9488',
		'button_hover_color'                             => '#0f766e',

		// Email sender
		'email_sender_name'                              => '',
		'email_sender_email'                             => '',

		// Payment
		'currency'                                       => 'USD',
		'payment_offline_enabled'                        => true,
		'payment_offline_title'                          => 'Pay in person',
		'payment_offline_description'                    => 'Pay at the time of your session.',

		// Notification toggles
		'notify_new_booking_confirmation'                => true,
		'notify_new_booking_alert'                       => true,
		'notify_cancellation_notice'                     => true,
		'notify_24_hour_reminder'                        => true,
		'notify_order_confirmed'                         => true,
		'notify_order_cancelled'                         => true,
		'notify_welcome_client'                          => true,
		'notify_session_booking_confirmation'            => true,
		'notify_session_completed_follow_up'             => true,
		'notify_session_cancelled_alert'                 => true,

		// Email templates (subject + body per notification)
		'email_tpl_new_booking_confirmation_subject'     => 'Your booking #{booking_id} has been received',
		'email_tpl_new_booking_confirmation_body'        => "Hi {client_name},\n\nThank you for booking <strong>{program_name}</strong>.\n\nBooking ID: #{booking_id}\nAmount: {amount} {currency}\nPayment method: {payment_method_title}\n\nWe will confirm once payment is verified.\n\nBest regards,\n{site_name}",
		'email_tpl_new_booking_alert_subject'            => 'New booking #{booking_id} from {client_name}',
		'email_tpl_new_booking_alert_body'               => "A new booking has been placed.\n\nBooking ID: #{booking_id}\nClient: {client_name} ({client_email})\nProgram: {program_name}\nAmount: {amount} {currency}\nPayment method: {payment_method_title}\n\nManage from the admin panel.\n\n{site_name}",
		'email_tpl_cancellation_notice_subject'          => 'Your session for {program_name} has been cancelled',
		'email_tpl_cancellation_notice_body'             => "Hi {client_name},\n\nYour session for <strong>{program_name}</strong> has been cancelled.\n\nDate: {scheduled_date}\nTime: {start_time} - {end_time}\n\nIf you have questions, please contact us.\n\nBest regards,\n{site_name}",
		'email_tpl_24h_reminder_subject'                 => 'Reminder: Session tomorrow',
		'email_tpl_24h_reminder_body'                    => "Hi {client_name},\n\nThis is a reminder that you have a session scheduled tomorrow for <strong>{program_name}</strong>.\n\nDate: {scheduled_date}\nTime: {start_time} - {end_time}\n\n{meeting_details}\n\nBest regards,\n{site_name}",
		'email_tpl_order_confirmed_subject'              => 'Your order #{order_id} is confirmed!',
		'email_tpl_order_confirmed_body'                 => "Hi {client_name},\n\nGreat news! Your payment has been verified and your booking for <strong>{program_name}</strong> is now active.\n\nOrder ID: #{order_id}\nAmount: {amount} {currency}\n\nYou can now access your program from your dashboard.\n\nBest regards,\n{site_name}",
		'email_tpl_order_cancelled_subject'              => 'Your order #{order_id} has been cancelled',
		'email_tpl_order_cancelled_body'                 => "Hi {client_name},\n\nYour order for <strong>{program_name}</strong> (#{order_id}) has been cancelled.\n\nIf you believe this is a mistake or have questions, please contact us.\n\nBest regards,\n{site_name}",
		'email_tpl_welcome_client_subject'               => 'Welcome to {site_name}!',
		'email_tpl_welcome_client_body'                  => "Hi {client_name},\n\nWelcome to <strong>{site_name}</strong>! Your client account has been created.\n\nSet your password and log in here: <a href=\"{set_password_url}\">{set_password_url}</a>\n\nThis link expires after 24 hours. If you did not request this account, you can safely ignore this email.\n\nBest regards,\n{site_name}",
		'email_tpl_session_booking_confirmation_subject' => 'Your session for {program_name} is confirmed',
		'email_tpl_session_booking_confirmation_body'    => "Hi {client_name},\n\nYour session for <strong>{program_name}</strong> has been booked.\n\nDate: {scheduled_date}\nTime: {start_time} - {end_time}\n\n{meeting_details}\n\nBest regards,\n{site_name}",
		'email_tpl_session_completed_follow_up_subject'  => 'Session completed: {program_name}',
		'email_tpl_session_completed_follow_up_body'     => "Hi {client_name},\n\nYour session for <strong>{program_name}</strong> on {scheduled_date} has been marked as completed.\n\nThank you for attending! If you have any feedback or questions, please don't hesitate to reach out.\n\nBest regards,\n{site_name}",
		'email_tpl_session_cancelled_alert_subject'      => 'Session cancelled: {program_name} with {client_name}',
		'email_tpl_session_cancelled_alert_body'         => "A session has been cancelled.\n\nClient: {client_name} ({client_email})\nProgram: {program_name}\nDate: {scheduled_date}\nTime: {start_time} - {end_time}\n\n{site_name}",
		// Google integration
		'google_meet_enabled'                            => false,

		// Pages
		'page_account'                                   => 0,
		'page_checkout'                                  => 0,

		// Privacy / GDPR
		'consent_required'                               => true,
		'consent_text'                                   => 'I agree to the {privacy_policy}.',
		'privacy_policy_version'                         => '1.0',

		// Misc
		'delete_data_on_uninstall'                       => false,

		// Advanced
		'send_anonymous_usage_data'                      => false,
	];

	private ?array $loaded = null;

	private function getExtendedDefaults(): array {
		return $this->applyFilters( 'allcoach:settings:defaults', self::DEFAULTS );
	}

	public function get( ?string $key = null ): mixed {
		$settings = $this->all();
		if ( $key === null || $key === '' ) {
			return $settings;
		}
		return $settings[ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}

	public function all(): array {
		$defaults = $this->getExtendedDefaults();
		if ( $this->loaded === null ) {
			$stored       = get_option( self::OPTION_KEY, [] );
			$this->loaded = is_array( $stored ) ? $stored : [];
		}
		$merged = array_merge( $defaults, array_intersect_key( $this->loaded, $defaults ) );

		if ( empty( $merged['email_sender_name'] ) ) {
			$merged['email_sender_name'] = get_bloginfo( 'name' );
		}
		if ( empty( $merged['email_sender_email'] ) ) {
			$merged['email_sender_email'] = get_option( 'admin_email' );
		}

		// The SDK's Logger module reads its own raw option, not ours - treat
		// that as the single source of truth instead of storing a second,
		// potentially out-of-sync copy in _allcoach_settings.
		$merged['send_anonymous_usage_data'] = get_option( $this->loggerFlagOption(), 'no' ) === 'yes';

		return $merged;
	}

	public function set( string $key, mixed $value ): void {
		if ( ! array_key_exists( $key, $this->getExtendedDefaults() ) ) {
			return;
		}
		$value = $this->sanitize( $key, $value );
		if ( $value === null ) {
			return;
		}
		$all         = $this->all();
		$all[ $key ] = $value;
		$this->persist( $all );
	}

	public function setMultiple( array $settings ): void {
		$all     = $this->all();
		$allowed = array_intersect_key( $settings, $this->getExtendedDefaults() );
		foreach ( $allowed as $key => $value ) {
			$sanitized = $this->sanitize( $key, $value );
			if ( $sanitized !== null ) {
				$all[ $key ] = $sanitized;
			}
		}
		$this->persist( $all );
	}

	public function getDefaults(): array {
		return $this->getExtendedDefaults();
	}

	/**
	 * @return list<string>
	 */
	/**
	 * @return list<string>
	 */
	public function getEnabledPaymentMethods(): array {
		$methods = [];
		if ( $this->get( 'payment_offline_enabled' ) ) {
			$methods[] = 'offline';
		}
		return $this->applyFilters( 'allcoach:settings:payment_methods', $methods );
	}

	public function getPaymentMethodTitle( string $methodId ): string {
		$title = match ( $methodId ) {
			'offline' => (string) $this->get( 'payment_offline_title' ),
			default   => $methodId,
		};
		return (string) $this->applyFilters( 'allcoach:settings:payment_method_title', $title, $methodId );
	}

	public function getPaymentMethodDescription( string $methodId ): string {
		$description = match ( $methodId ) {
			'offline' => (string) $this->get( 'payment_offline_description' ),
			default   => '',
		};
		return (string) $this->applyFilters( 'allcoach:settings:payment_method_description', $description, $methodId );
	}

	/**
	 * Get REST API schema for plugin settings.
	 *
	 * Each setting key contains a schema definition compatible with
	 * the WordPress REST API argument schema.
	 *
	 * @return array<string, array{
	 *     type: string,
	 *     default: mixed,
	 *     description?: string,
	 *     minimum?: int,
	 *     maximum?: int
	 * }>
	 */
	public function getSchema(): array {
		$schema = [
			'primary_color'                               => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['primary_color'],
				'description'       => __( 'Primary brand color used across the frontend.', 'allcoach' ),
				'sanitize_callback' => [ self::class, 'sanitizeHexColor' ],
			],
			'button_color'                                => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['button_color'],
				'description'       => __( 'Button color for the frontend.', 'allcoach' ),
				'sanitize_callback' => [ self::class, 'sanitizeHexColor' ],
			],
			'button_hover_color'                          => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['button_hover_color'],
				'description'       => __( 'Button hover color for the frontend.', 'allcoach' ),
				'sanitize_callback' => [ self::class, 'sanitizeHexColor' ],
			],
			'currency'                                    => [
				'type'        => 'string',
				'default'     => 'USD',
				'description' => __( 'Default currency for programs and bookings.', 'allcoach' ),
			],
			'payment_offline_enabled'                     => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Enable offline payment method.', 'allcoach' ),
			],
			'payment_offline_title'                       => [
				'type'        => 'string',
				'default'     => 'Pay in person',
				'description' => __( 'Display title for offline payment.', 'allcoach' ),
			],
			'payment_offline_description'                 => [
				'type'        => 'string',
				'default'     => 'Pay at the time of your session.',
				'description' => __( 'Description shown to clients for offline payment.', 'allcoach' ),
			],
			'email_sender_name'                           => [
				'type'        => 'string',
				'default'     => '',
				'description' => __( 'Sender name for outgoing emails.', 'allcoach' ),
			],
			'email_sender_email'                          => [
				'type'        => 'string',
				'default'     => '',
				'description' => __( 'Sender email address for outgoing emails.', 'allcoach' ),
			],
			'notify_new_booking_confirmation'             => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'New booking confirmation: sent to client when a booking is received.', 'allcoach' ),
			],
			'notify_new_booking_alert'                    => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'New booking alert: sent to admin when a new booking is placed.', 'allcoach' ),
			],
			'notify_cancellation_notice'                  => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Session cancellation notice: sent to client when a session is cancelled.', 'allcoach' ),
			],
			'notify_24_hour_reminder'                     => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( '24-hour reminder: sent to client 24 hours before a scheduled session.', 'allcoach' ),
			],
			'email_tpl_new_booking_confirmation_subject'  => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_new_booking_confirmation_subject'],
				'description' => __( 'Subject for new booking confirmation.', 'allcoach' ),
			],
			'email_tpl_new_booking_confirmation_body'     => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_new_booking_confirmation_body'],
				'description'       => __( 'Body template for new booking confirmation.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_new_booking_alert_subject'         => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_new_booking_alert_subject'],
				'description' => __( 'Subject for new booking alert.', 'allcoach' ),
			],
			'email_tpl_new_booking_alert_body'            => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_new_booking_alert_body'],
				'description'       => __( 'Body template for new booking alert.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_cancellation_notice_subject'       => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_cancellation_notice_subject'],
				'description' => __( 'Subject for cancellation notice.', 'allcoach' ),
			],
			'email_tpl_cancellation_notice_body'          => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_cancellation_notice_body'],
				'description'       => __( 'Body template for cancellation notice.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_24h_reminder_subject'              => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_24h_reminder_subject'],
				'description' => __( 'Subject for 24-hour session reminder.', 'allcoach' ),
			],
			'email_tpl_24h_reminder_body'                 => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_24h_reminder_body'],
				'description'       => __( 'Body template for 24-hour session reminder.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'notify_order_confirmed'                      => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Order confirmed: sent to client when their payment is verified.', 'allcoach' ),
			],
			'notify_order_cancelled'                      => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Order cancelled: sent to client when their order is cancelled.', 'allcoach' ),
			],
			'notify_welcome_client'                       => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Welcome email: sent to client when their account is created.', 'allcoach' ),
			],
			'notify_session_booking_confirmation'         => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Session booking confirmation: sent to client immediately when a session is booked.', 'allcoach' ),
			],
			'notify_session_completed_follow_up'          => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Session completed follow-up: sent to client after a session is marked as completed.', 'allcoach' ),
			],
			'notify_session_cancelled_alert'              => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Session cancelled alert: sent to admin when a session is cancelled.', 'allcoach' ),
			],
			'email_tpl_order_confirmed_subject'           => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_order_confirmed_subject'],
				'description' => __( 'Subject for order confirmed notification.', 'allcoach' ),
			],
			'email_tpl_order_confirmed_body'              => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_order_confirmed_body'],
				'description'       => __( 'Body template for order confirmed notification.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_order_cancelled_subject'           => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_order_cancelled_subject'],
				'description' => __( 'Subject for order cancelled notification.', 'allcoach' ),
			],
			'email_tpl_order_cancelled_body'              => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_order_cancelled_body'],
				'description'       => __( 'Body template for order cancelled notification.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_welcome_client_subject'            => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_welcome_client_subject'],
				'description' => __( 'Subject for welcome client email.', 'allcoach' ),
			],
			'email_tpl_welcome_client_body'               => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_welcome_client_body'],
				'description'       => __( 'Body template for welcome client email.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_session_booking_confirmation_subject' => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_session_booking_confirmation_subject'],
				'description' => __( 'Subject for session booking confirmation.', 'allcoach' ),
			],
			'email_tpl_session_booking_confirmation_body' => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_session_booking_confirmation_body'],
				'description'       => __( 'Body template for session booking confirmation.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_session_completed_follow_up_subject' => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_session_completed_follow_up_subject'],
				'description' => __( 'Subject for session completed follow-up.', 'allcoach' ),
			],
			'email_tpl_session_completed_follow_up_body'  => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_session_completed_follow_up_body'],
				'description'       => __( 'Body template for session completed follow-up.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'email_tpl_session_cancelled_alert_subject'   => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['email_tpl_session_cancelled_alert_subject'],
				'description' => __( 'Subject for session cancelled alert.', 'allcoach' ),
			],
			'email_tpl_session_cancelled_alert_body'      => [
				'type'              => 'string',
				'default'           => self::DEFAULTS['email_tpl_session_cancelled_alert_body'],
				'description'       => __( 'Body template for session cancelled alert.', 'allcoach' ),
				'sanitize_callback' => 'wp_kses_post',
			],
			'google_meet_enabled'                         => [
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Auto-generate Google Meet links for session bookings.', 'allcoach' ),
			],
			'send_anonymous_usage_data'                   => [
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Help improve the plugin by sharing anonymous usage statistics. No personal data is collected and data is never shared with third parties.', 'allcoach' ),
			],
			'consent_required'                            => [
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Require visitors to accept the privacy policy before checkout and booking.', 'allcoach' ),
			],
			'consent_text'                                => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['consent_text'],
				'description' => __( 'Consent checkbox label. Use {privacy_policy} to insert a link to the privacy policy page.', 'allcoach' ),
			],
			'privacy_policy_version'                      => [
				'type'        => 'string',
				'default'     => self::DEFAULTS['privacy_policy_version'],
				'description' => __( 'Current privacy policy version, recorded with each consent.', 'allcoach' ),
			],
			'delete_data_on_uninstall'                    => [
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Permanently delete all AllCoach data (programs, orders, bookings, clients data and settings) when the plugin is deleted. This cannot be undone.', 'allcoach' ),
			],
		];
		return $this->applyFilters( 'allcoach:settings:schema', $schema );
	}

	private function sanitize( string $key, mixed $value ): mixed {
		if ( in_array( $key, self::HTML_BODY_KEYS, true ) ) {
			return is_string( $value ) ? wp_kses_post( $value ) : (string) $value;
		}

		if ( in_array( $key, self::HEX_COLOR_KEYS, true ) ) {
			return self::sanitizeHexColor( $value, null, $key );
		}

		$default = $this->getExtendedDefaults()[ $key ] ?? null;
		if ( is_bool( $default ) ) {
			return (bool) $value;
		}
		if ( is_int( $default ) ) {
			$v = (int) $value;
			return max( 1, min( 100, $v ) );
		}
		if ( is_string( $default ) ) {
			$value = is_string( $value ) ? sanitize_text_field( $value ) : (string) $value;
			$enum  = $this->getSchema()[ $key ]['enum'] ?? null;

			if ( is_array( $enum ) && ! in_array( $value, $enum, true ) ) {
				return $default;
			}

			return $value;
		}
		return $value;
	}

	/**
	 * REST-compatible sanitize callback for hex color settings. Falls back to
	 * the setting's own default when the value isn't a valid #rrggbb hex color.
	 */
	public static function sanitizeHexColor( mixed $value, mixed $request = null, ?string $param = null ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return strtolower( $value );
		}

		return $param !== null && isset( self::DEFAULTS[ $param ] ) ? self::DEFAULTS[ $param ] : '';
	}

	/**
	 * Lighten (positive percent) or darken (negative percent) a #rrggbb hex color.
	 *
	 * Lightening blends each channel toward white (255) rather than scaling it
	 * up multiplicatively — scaling up overshoots and clips unevenly across
	 * channels for large percentages, distorting the hue into an oversaturated
	 * color instead of a pale tint. Darkening scales toward black, which does
	 * not have that problem.
	 */
	public static function colorLuminance( string $hex, float $percent ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) !== 6 ) {
			return '#' . str_repeat( '0', 6 );
		}

		$rgb = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$channel = hexdec( substr( $hex, $i * 2, 2 ) );
			$target  = $percent >= 0 ? 255 : 0;
			$channel = (int) round( min( max( 0, $channel + ( ( $target - $channel ) * abs( $percent ) ) ), 255 ) );
			$rgb    .= str_pad( dechex( $channel ), 2, '0', STR_PAD_LEFT );
		}

		return $rgb;
	}

	private function persist( array $settings ): void {
		if ( array_key_exists( 'send_anonymous_usage_data', $settings ) ) {
			update_option( $this->loggerFlagOption(), $settings['send_anonymous_usage_data'] ? 'yes' : 'no' );
		}

		$toStore = array_intersect_key( $settings, $this->getExtendedDefaults() );
		update_option( self::OPTION_KEY, $toStore );
		$this->loaded = $toStore;

		$this->doAction( 'allcoach:settings:updated', $toStore );
	}

	/**
	 * Matches ThemeGrillSDK\Product::get_key() . '_logger_flag', the raw
	 * option the SDK's Logger module reads to decide whether to send
	 * anonymous usage data.
	 */
	private function loggerFlagOption(): string {
		$slug = dirname( Constants::pluginBasename() );
		$key  = str_replace( '-', '_', strtolower( trim( $slug ) ) );

		return $key . '_logger_flag';
	}
}
