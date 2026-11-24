<?php

declare(strict_types=1);

namespace AllCoach\Modules\PayPal;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\PaymentGateway;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItem;
use AllCoach\Support\Logger;

class PayPalPaymentGateway implements PaymentGateway {

	private const API_BASE_LIVE    = 'https://api-m.paypal.com';
	private const API_BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';

	public function __construct(
		private readonly SettingsManager $settingsManager,
		private readonly Logger $logger,
	) {}

	public function getId(): string {
		return 'paypal';
	}

	public function getName(): string {
		return __( 'PayPal', 'allcoach' );
	}

	public function isEnabled(): bool {
		return (bool) $this->settingsManager->get( 'payment_paypal_enabled' );
	}

	/**
	 * @return array{success: bool, transaction_id: string|null, message: string}
	 */
	public function processPaymentForOrder( Order $order, OrderItem $orderItem ): array {
		$paypalOrderId = $this->createOrder( $order, $orderItem );

		if ( $paypalOrderId === null ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'message'        => __( 'Failed to create PayPal order.', 'allcoach' ),
			];
		}

		return [
			'success'        => true,
			'transaction_id' => $paypalOrderId,
			'message'        => __( 'PayPal order created. Awaiting client payment.', 'allcoach' ),
		];
	}

	/**
	 * Create a PayPal order via Orders API v2.
	 * Stores our internal order ID as custom_id on the purchase unit for webhook correlation.
	 */
	public function createOrder( Order $order, OrderItem $_orderItem ): ?string {
		$accessToken = $this->getAccessToken();
		if ( $accessToken === null ) {
			return null;
		}

		$currency = strtoupper( $order->getCurrency() );
		$amount   = number_format( (float) $order->getTotal(), 2, '.', '' );

		$body = [
			'intent'         => $this->isAuthorizeAction() ? 'AUTHORIZE' : 'CAPTURE',
			'purchase_units' => [
				[
					'custom_id'   => (string) $order->getId(),
					/* translators: %d: order ID */
					'description' => sprintf( __( 'AllCoach Order #%d', 'allcoach' ), $order->getId() ),
					'amount'      => [
						'currency_code' => $currency,
						'value'         => $amount,
					],
				],
			],
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'shipping_preference' => $this->resolveShippingPreference(),
					],
				],
			],
		];

		$response = wp_remote_post(
			$this->apiBase() . '/v2/checkout/orders',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return $data['id'] ?? null;
	}

	/**
	 * Complete a PayPal order after client approval using the configured payment action.
	 *
	 * @return array{success: bool, transaction_id: string|null, action: string}
	 */
	public function completeOrder( string $paypalOrderId ): array {
		return $this->isAuthorizeAction()
			? $this->authorizeOrder( $paypalOrderId )
			: $this->captureOrder( $paypalOrderId );
	}

	/**
	 * Capture a PayPal order after client approval.
	 *
	 * @return array{success: bool, transaction_id: string|null, action: string}
	 */
	private function captureOrder( string $paypalOrderId ): array {
		$accessToken = $this->getAccessToken();
		if ( $accessToken === null ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture',
			];
		}

		$response = wp_remote_post(
			$this->apiBase() . '/v2/checkout/orders/' . rawurlencode( $paypalOrderId ) . '/capture',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				],
				'body'    => '{}',
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture',
			];
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = $data['status'] ?? '';

		if ( $status !== 'COMPLETED' ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture',
			];
		}

		$captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
		if ( ! is_string( $captureId ) || $captureId === '' ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture',
			];
		}

		return [
			'success'        => true,
			'transaction_id' => $captureId,
			'action'         => 'capture',
		];
	}

	/**
	 * Authorize a PayPal order after client approval without capturing funds.
	 *
	 * @return array{success: bool, transaction_id: string|null, action: string}
	 */
	private function authorizeOrder( string $paypalOrderId ): array {
		$accessToken = $this->getAccessToken();
		if ( $accessToken === null ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'authorize',
			];
		}

		$response = wp_remote_post(
			$this->apiBase() . '/v2/checkout/orders/' . rawurlencode( $paypalOrderId ) . '/authorize',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				],
				'body'    => '{}',
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'authorize',
			];
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = $data['status'] ?? '';

		if ( $status !== 'COMPLETED' ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'authorize',
			];
		}

		$authorizationId = $data['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
		if ( ! is_string( $authorizationId ) || $authorizationId === '' ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'authorize',
			];
		}

		return [
			'success'        => true,
			'transaction_id' => $authorizationId,
			'action'         => 'authorize',
		];
	}

	/**
	 * Capture funds from a previously authorized PayPal payment.
	 *
	 * @return array{success: bool, transaction_id: string|null, action: string}
	 */
	public function captureAuthorization( string $authorizationId ): array {
		$accessToken = $this->getAccessToken();
		if ( $accessToken === null ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture_authorization',
			];
		}

		$response = wp_remote_post(
			$this->apiBase() . '/v2/payments/authorizations/' . rawurlencode( $authorizationId ) . '/capture',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				],
				'body'    => '{}',
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture_authorization',
			];
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = $data['status'] ?? '';

		if ( $status !== 'COMPLETED' ) {
			if ( $this->isAlreadyCapturedError( $data ) ) {
				return [
					'success'        => true,
					'transaction_id' => $authorizationId,
					'action'         => 'capture_authorization',
				];
			}

			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture_authorization',
			];
		}

		$captureId = $data['id'] ?? null;
		if ( ! is_string( $captureId ) || $captureId === '' ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'action'         => 'capture_authorization',
			];
		}

		return [
			'success'        => true,
			'transaction_id' => $captureId,
			'action'         => 'capture_authorization',
		];
	}

	private function isAlreadyCapturedError( mixed $data ): bool {
		if ( ! is_array( $data ) || ! is_array( $data['details'] ?? null ) ) {
			return false;
		}

		foreach ( $data['details'] as $detail ) {
			if ( ( $detail['issue'] ?? '' ) === 'AUTHORIZATION_ALREADY_CAPTURED' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify a PayPal webhook signature via the PayPal verification API.
	 */
	public function verifyWebhookSignature( array $headers, string $rawBody ): bool {
		$webhookId = (string) $this->settingsManager->get( 'payment_paypal_webhook_id' );
		if ( $webhookId === '' ) {
			return false;
		}

		$requiredHeaders = [
			'PAYPAL-AUTH-ALGO',
			'PAYPAL-CERT-URL',
			'PAYPAL-TRANSMISSION-ID',
			'PAYPAL-TRANSMISSION-SIG',
			'PAYPAL-TRANSMISSION-TIME',
		];
		foreach ( $requiredHeaders as $key ) {
			if ( empty( $headers[ $key ] ) ) {
				$this->logger->warning( 'PayPal webhook verification missing header', [ 'header' => $key ] );
				return false;
			}
		}

		json_decode( $rawBody );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->warning(
				'PayPal webhook verification: invalid JSON body',
				[
					'json_error' => json_last_error_msg(),
				]
			);
			return false;
		}

		$accessToken = $this->getAccessToken();
		if ( $accessToken === null ) {
			return false;
		}

		$body = sprintf(
			'{"auth_algo":%s,"cert_url":%s,"transmission_id":%s,"transmission_sig":%s,"transmission_time":%s,"webhook_id":%s,"webhook_event":%s}',
			wp_json_encode( $headers['PAYPAL-AUTH-ALGO'] ),
			wp_json_encode( $headers['PAYPAL-CERT-URL'] ),
			wp_json_encode( $headers['PAYPAL-TRANSMISSION-ID'] ),
			wp_json_encode( $headers['PAYPAL-TRANSMISSION-SIG'] ),
			wp_json_encode( $headers['PAYPAL-TRANSMISSION-TIME'] ),
			wp_json_encode( $webhookId ),
			$rawBody
		);

		$response = wp_remote_post(
			$this->apiBase() . '/v1/notifications/verify-webhook-signature',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'PayPal webhook verification request failed',
				[
					'error' => $response->get_error_message(),
				]
			);
			return false;
		}

		$statusCode = wp_remote_retrieve_response_code( $response );
		$data       = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $statusCode !== 200 ) {
			$this->logger->warning(
				'PayPal webhook verification non-200 response',
				[
					'status' => $statusCode,
					'body'   => $data,
				]
			);
			return false;
		}

		$verified = ( $data['verification_status'] ?? '' ) === 'SUCCESS';

		if ( ! $verified ) {
			$this->logger->warning(
				'PayPal webhook signature verification failed',
				[
					'response' => $data,
				]
			);
		}

		return $verified;
	}

	/**
	 * Test PayPal credentials without using stored settings.
	 * Accepts explicit creds so user can verify before saving.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function testConnection( string $clientId, string $secret, bool $sandbox ): array {
		if ( $clientId === '' || $secret === '' ) {
			return [
				'success' => false,
				'message' => __( 'Client ID and secret are required.', 'allcoach' ),
			];
		}

		$base = $sandbox ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

		$response = wp_remote_post(
			$base . '/v1/oauth2/token',
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $clientId . ':' . $secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['access_token'] ) ) {
			$detail = $data['error_description'] ?? $data['message'] ?? __( 'Invalid credentials.', 'allcoach' );
			return [
				'success' => false,
				'message' => (string) $detail,
			];
		}

		return [
			'success' => true,
			'message' => $sandbox
				? __( 'Connected to PayPal Sandbox.', 'allcoach' )
				: __( 'Connected to PayPal Live.', 'allcoach' ),
		];
	}

	private function getAccessToken(): ?string {
		$cacheKey = 'allcoach_paypal_token_' . ( $this->isSandbox() ? 'sandbox' : 'live' );
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return $cached;
		}

		$clientId = (string) $this->settingsManager->get( 'payment_paypal_client_id' );
		$secret   = (string) $this->settingsManager->get( 'payment_paypal_secret' );

		if ( $clientId === '' || $secret === '' ) {
			return null;
		}

		$response = wp_remote_post(
			$this->apiBase() . '/v1/oauth2/token',
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $clientId . ':' . $secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data        = json_decode( wp_remote_retrieve_body( $response ), true );
		$accessToken = $data['access_token'] ?? null;
		$expiresIn   = (int) ( $data['expires_in'] ?? 3600 );

		if ( $accessToken === null ) {
			return null;
		}

		// Cache slightly under expiry to avoid using an expired token.
		set_transient( $cacheKey, $accessToken, $expiresIn - 60 );

		return $accessToken;
	}

	private function resolveShippingPreference(): string {
		return match ( (string) $this->settingsManager->get( 'payment_paypal_shipping_preference' ) ) {
			'get_from_file' => 'GET_FROM_FILE',
			default           => 'NO_SHIPPING',
		};
	}

	public function getPaymentAction(): string {
		return $this->isAuthorizeAction() ? 'authorize' : 'capture';
	}

	private function isAuthorizeAction(): bool {
		return strtolower( (string) $this->settingsManager->get( 'payment_paypal_payment_action' ) ) === 'authorize';
	}

	private function apiBase(): string {
		return $this->isSandbox() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;
	}

	private function isSandbox(): bool {
		return (bool) $this->settingsManager->get( 'payment_paypal_sandbox' );
	}
}
