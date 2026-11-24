<?php

declare(strict_types=1);

namespace AllCoach\Modules\PayPal;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Order\Order;
use AllCoach\Modules\AbstractAddon;

class PayPalAddon extends AbstractAddon {

	protected string $id             = 'paypal';
	protected string $name           = 'PayPal';
	protected string $description    = 'Accept payments via PayPal REST API. Supports capture and authorize payment actions.';
	protected string $version        = '1.0.0';
	protected string $category       = 'payment';
	protected bool $enabledByDefault = true;
	protected string $icon           = 'money-alt';
	protected string $docsUrl        = '';
	protected string $settingsRoute  = '/settings/payment';

	/**
	 * Always runs — registers PayPal settings and gates payment method access.
	 */
	public function register(): void {
		// Inject PayPal defaults so SettingsManager can persist/read them.
		$this->addFilter(
			'allcoach:settings:defaults',
			function ( array $defaults ): array {
				return array_merge(
					$defaults,
					[
						'payment_paypal_enabled'        => false,
						'payment_paypal_title'          => 'PayPal',
						'payment_paypal_description'    => 'Pay securely via PayPal.',
						'payment_paypal_client_id'      => '',
						'payment_paypal_secret'         => '',
						'payment_paypal_sandbox'        => true,
						'payment_paypal_webhook_id'     => '',
						'payment_paypal_payment_action' => 'capture',
						'payment_paypal_shipping_preference' => 'no_shipping',
					]
				);
			}
		);

		// Inject PayPal REST schema for the settings endpoint.
		$this->addFilter(
			'allcoach:settings:schema',
			function ( array $schema ): array {
				return array_merge(
					$schema,
					[
						'payment_paypal_enabled'        => [
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Enable PayPal payment gateway.', 'allcoach' ),
						],
						'payment_paypal_title'          => [
							'type'        => 'string',
							'default'     => 'PayPal',
							'description' => __( 'Display title for PayPal payment.', 'allcoach' ),
						],
						'payment_paypal_description'    => [
							'type'        => 'string',
							'default'     => 'Pay securely via PayPal.',
							'description' => __( 'Description shown to clients for PayPal payment.', 'allcoach' ),
						],
						'payment_paypal_client_id'      => [
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'PayPal REST API client ID.', 'allcoach' ),
						],
						'payment_paypal_secret'         => [
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'PayPal REST API secret key.', 'allcoach' ),
						],
						'payment_paypal_sandbox'        => [
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Use PayPal sandbox (test) environment.', 'allcoach' ),
						],
						'payment_paypal_webhook_id'     => [
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'PayPal webhook ID for signature verification.', 'allcoach' ),
						],
						'payment_paypal_payment_action' => [
							'type'        => 'string',
							'default'     => 'capture',
							'enum'        => [ 'capture', 'authorize' ],
							'description' => __( 'Whether to capture payment immediately or only authorize.', 'allcoach' ),
						],
						'payment_paypal_shipping_preference' => [
							'type'        => 'string',
							'default'     => 'no_shipping',
							'enum'        => [ 'no_shipping', 'get_from_file' ],
							'description' => __( 'Shipping address collection preference.', 'allcoach' ),
						],
					]
				);
			}
		);

		// Add 'paypal' to enabled payment methods when addon + setting are both on.
		$this->addFilter(
			'allcoach:settings:payment_methods',
			function ( array $methods ): array {
				if ( ! $this->isEnabled() ) {
					return $methods;
				}
				/** @var SettingsManager $settings */
				$settings = $this->get( SettingsManager::class );
				if ( $settings->get( 'payment_paypal_enabled' ) ) {
					$methods[] = 'paypal';
				}
				return $methods;
			}
		);

		// Provide PayPal payment method title.
		$this->addFilter(
			'allcoach:settings:payment_method_title',
			function ( string $title, string $methodId ): string {
				if ( $methodId !== 'paypal' ) {
					return $title;
				}
				/** @var SettingsManager $settings */
				$settings = $this->get( SettingsManager::class );
				return (string) $settings->get( 'payment_paypal_title' );
			},
			10,
			2
		);

		// Provide PayPal payment method description.
		$this->addFilter(
			'allcoach:settings:payment_method_description',
			function ( string $description, string $methodId ): string {
				if ( $methodId !== 'paypal' ) {
					return $description;
				}
				/** @var SettingsManager $settings */
				$settings = $this->get( SettingsManager::class );
				return (string) $settings->get( 'payment_paypal_description' );
			},
			10,
			2
		);
	}

	/**
	 * Only runs when addon is enabled.
	 */
	public function boot(): void {
		$this->addFilter(
			'allcoach:rest:controllers',
			function ( array $controllers ): array {
				$controllers[] = PayPalWebhookController::class;
				$controllers[] = PayPalOrderController::class;
				return $controllers;
			}
		);

		$this->addFilter(
			'allcoach:order:confirm_transaction_id',
			function ( mixed $transactionId, Order $order ): mixed {
				$paymentReference = (string) $order->getPaymentReference();

				if ( $order->getPaymentMethod() !== 'paypal' || ! str_starts_with( $paymentReference, 'AUTH:' ) ) {
					return $transactionId;
				}

				$authorizationId = substr( $paymentReference, 5 );

				/** @var PayPalPaymentGateway $gateway */
				$gateway = $this->get( PayPalPaymentGateway::class );
				$result  = $gateway->captureAuthorization( $authorizationId );

				if ( ! $result['success'] ) {
					throw new \RuntimeException(
						esc_html__( 'PayPal authorization capture failed. Order was not confirmed.', 'allcoach' )
					);
				}

				return $result['transaction_id'];
			},
			10,
			2
		);
	}
}
