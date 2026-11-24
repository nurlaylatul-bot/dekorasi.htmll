<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class Mailer {

	use Hooks;

	public function __construct(
		private readonly SettingsManager $settings,
		private readonly Logger $logger,
	) {}

	public function send( string $to, string $subject, string $body, array $headers = [] ): bool {
		$senderName  = $this->settings->get( 'email_sender_name' ) ?: get_bloginfo( 'name' );
		$senderEmail = $this->settings->get( 'email_sender_email' ) ?: get_option( 'admin_email' );

		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$headers[] = sprintf( 'From: %s <%s>', $senderName, $senderEmail );

		/**
		 * Filters the email headers before sending.
		 *
		 * @param string[] $headers Email headers.
		 * @param string   $to      Recipient address.
		 * @param string   $subject Email subject.
		 */
		$headers = $this->applyFilters( 'allcoach:mail:headers', $headers, $to, $subject );

		$htmlBody = $this->wrapInLayout( $body, $subject );

		/**
		 * Filters the final HTML body before sending.
		 *
		 * @param string $htmlBody The wrapped HTML email body.
		 * @param string $to       Recipient address.
		 * @param string $subject  Email subject.
		 */
		$htmlBody = $this->applyFilters( 'allcoach:mail:body', $htmlBody, $to, $subject );

		$sent = wp_mail( $to, $subject, $htmlBody, $headers );

		if ( $sent ) {
			/** Fires after an email has been sent successfully. */
			$this->doAction( 'allcoach:mail:sent', $to, $subject );
		} else {
			/** Fires when an email fails to send. */
			$this->doAction( 'allcoach:mail:failed', $to, $subject );

			$this->logger->error(
				'Failed to send email',
				[
					'to'      => $to,
					'subject' => $subject,
				]
			);
		}

		return $sent;
	}

	/**
	 * @param array<string, string> $vars
	 */
	public function interpolate( string $template, array $vars ): string {
		$replacements = [];
		foreach ( $vars as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}
		return strtr( $template, $replacements );
	}

	private function wrapInLayout( string $body, string $subject ): string {
		$siteName = esc_html( get_bloginfo( 'name' ) );
		$bodyHtml = nl2br( $body );

		return '<!DOCTYPE html>'
			. '<html lang="en">'
			. '<head>'
			. '<meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
			. '<title>' . $subject . '</title>'
			. '</head>'
			. '<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:32px 16px;">'
			. '<tr><td align="center">'
			. '<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">'
			. '<tr><td style="background-color:#0d9488;padding:24px 32px;">'
			. '<h1 style="margin:0;color:#ffffff;font-size:18px;font-weight:600;">' . $siteName . '</h1>'
			. '</td></tr>'
			. '<tr><td style="padding:32px;color:#374151;font-size:14px;line-height:1.6;">'
			. $bodyHtml
			. '</td></tr>'
			. '<tr><td style="padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center;">'
			. '<p style="margin:0;color:#9ca3af;font-size:12px;">&copy; ' . $siteName . '</p>'
			. '</td></tr>'
			. '</table>'
			. '</td></tr>'
			. '</table>'
			. '</body>'
			. '</html>';
	}
}
