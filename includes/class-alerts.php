<?php
/**
 * Technical alert notifications for operational issues.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Alerts {

	/**
	 * Send a technical alert email when enabled.
	 *
	 * @param string $subject Alert subject.
	 * @param string $message Alert message.
	 * @param array  $context Optional context metadata.
	 * @return void
	 */
	public static function send( $subject, $message, $context = array() ) {
		if ( ! Matrix_Donations_Settings::is_technical_alerts_enabled() ) {
			return;
		}

		$recipients = self::get_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		$subject = sanitize_text_field( (string) $subject );
		$body    = sanitize_textarea_field( (string) $message );
		if ( ! empty( $context ) && is_array( $context ) ) {
			$body .= "\n\nContext:\n" . wp_json_encode( $context );
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		foreach ( $recipients as $to ) {
			wp_mail( $to, $subject, $body, $headers );
		}
	}

	/**
	 * Resolve alert recipients from settings.
	 *
	 * @return array
	 */
	private static function get_recipients() {
		$raw = (string) Matrix_Donations_Settings::get( 'technical_alert_emails' );
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$emails = preg_split( '/[\s,;]+/', $raw );
		if ( ! is_array( $emails ) ) {
			return array();
		}

		$clean = array();
		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( $email && is_email( $email ) ) {
				$clean[] = $email;
			}
		}
		return array_values( array_unique( $clean ) );
	}
}
