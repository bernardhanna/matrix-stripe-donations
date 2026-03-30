<?php
/**
 * Donation email notifications.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Notifications {

	/**
	 * Send admin and donor emails for successful donations.
	 *
	 * @param array $donation Donation payload.
	 * @return void
	 */
	public static function send_success_emails( $donation ) {
		$status = sanitize_text_field( $donation['status'] ?? '' );
		if ( 'paid' !== $status ) {
			return;
		}

		$placeholders = self::build_placeholders( $donation );
		self::send_admin_email( $placeholders );
		self::send_donor_email( $donation, $placeholders );
	}

	/**
	 * Send test versions of admin and donor emails.
	 *
	 * @param string $test_recipient Optional recipient for donor template email.
	 * @return bool True when at least one email send call succeeds.
	 */
	public static function send_test_emails( $test_recipient = '' ) {
		$recipient = sanitize_email( $test_recipient );
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			$recipient = sanitize_email( get_option( 'admin_email' ) );
		}

		$donation = array(
			'status'           => 'paid',
			'donor_first_name' => __( 'Test', 'matrix-donations' ),
			'donor_last_name'  => __( 'Donor', 'matrix-donations' ),
			'donor_email'      => $recipient,
			'donation_type'    => 'single',
			'frequency'        => '',
			'currency'         => 'eur',
			'amount_cents'     => 5000,
		);

		$placeholders = self::build_placeholders( $donation );
		$admin_sent   = self::send_admin_email( $placeholders );
		$donor_sent   = self::send_donor_email( $donation, $placeholders );

		return $admin_sent || $donor_sent;
	}

	/**
	 * Build reusable placeholder values.
	 *
	 * @param array $donation Donation payload.
	 * @return array
	 */
	private static function build_placeholders( $donation ) {
		$first_name  = sanitize_text_field( $donation['donor_first_name'] ?? '' );
		$last_name   = sanitize_text_field( $donation['donor_last_name'] ?? '' );
		$email       = sanitize_email( $donation['donor_email'] ?? '' );
		$currency    = strtoupper( sanitize_text_field( $donation['currency'] ?? 'eur' ) );
		$amount_cents = absint( $donation['amount_cents'] ?? 0 );
		$amount      = number_format_i18n( $amount_cents / 100, 2 );
		$full_name   = trim( $first_name . ' ' . $last_name );
		if ( '' === $full_name ) {
			$full_name = $email;
		}

		return array(
			'{first_name}'    => $first_name,
			'{last_name}'     => $last_name,
			'{full_name}'     => $full_name,
			'{email}'         => $email,
			'{donation_type}' => sanitize_text_field( $donation['donation_type'] ?? 'single' ),
			'{frequency}'     => sanitize_text_field( $donation['frequency'] ?? '' ),
			'{currency}'      => $currency,
			'{amount}'        => $amount,
			'{amount_cents}'  => (string) $amount_cents,
			'{amount_with_currency}' => trim( $currency . ' ' . $amount ),
			'{site_name}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'      => home_url(),
			'{date}'          => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
		);
	}

	/**
	 * Send notification to admin recipients.
	 *
	 * @param array $placeholders Placeholder values.
	 * @return void
	 */
	private static function send_admin_email( $placeholders ) {
		$recipient_setting = Matrix_Donations_Settings::get( 'notification_admin_emails' );
		$recipients        = self::parse_emails( (string) $recipient_setting );
		if ( empty( $recipients ) ) {
			$fallback = sanitize_email( get_option( 'admin_email' ) );
			if ( $fallback ) {
				$recipients[] = $fallback;
			}
		}

		if ( empty( $recipients ) ) {
			return false;
		}

		$subject_template = (string) Matrix_Donations_Settings::get( 'notification_admin_subject' );
		$body_template    = (string) Matrix_Donations_Settings::get( 'notification_admin_body' );
		$subject          = self::replace_placeholders( $subject_template, $placeholders );
		$body             = self::replace_placeholders( $body_template, $placeholders );

		$any_sent = false;
		foreach ( $recipients as $recipient ) {
			$sent = self::send_mail( $recipient, $subject, $body, 'admin' );
			if ( $sent ) {
				$any_sent = true;
			}
		}
		return $any_sent;
	}

	/**
	 * Send thank-you message to donor.
	 *
	 * @param array $donation     Donation payload.
	 * @param array $placeholders Placeholder values.
	 * @return void
	 */
	private static function send_donor_email( $donation, $placeholders ) {
		$recipient = sanitize_email( $donation['donor_email'] ?? '' );
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			return false;
		}

		$subject_template = (string) Matrix_Donations_Settings::get( 'notification_user_subject' );
		$body_template    = (string) Matrix_Donations_Settings::get( 'notification_user_body' );
		$subject          = self::replace_placeholders( $subject_template, $placeholders );
		$body             = self::replace_placeholders( $body_template, $placeholders );

		return self::send_mail( $recipient, $subject, $body, 'donor' );
	}

	/**
	 * Replace known placeholders in a template string.
	 *
	 * @param string $template     Template text.
	 * @param array  $placeholders Placeholder values.
	 * @return string
	 */
	private static function replace_placeholders( $template, $placeholders ) {
		$template = (string) $template;
		if ( '' === $template ) {
			return '';
		}
		return strtr( $template, $placeholders );
	}

	/**
	 * Parse comma/space/semicolon separated emails.
	 *
	 * @param string $value Raw email list.
	 * @return array
	 */
	private static function parse_emails( $value ) {
		$emails = preg_split( '/[\s,;]+/', (string) $value );
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

	/**
	 * Send email and log failures when debug mode is enabled.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Message subject.
	 * @param string $body    Message body.
	 * @param string $type    Mail type label.
	 * @return void
	 */
	private static function send_mail( $to, $subject, $body, $type ) {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );
		if ( ! $sent ) {
			Matrix_Donations_Logger::log(
				'error',
				'Donation email send failed',
				array(
					'type' => $type,
					'to'   => $to,
				)
			);
			Matrix_Donations_Alerts::send(
				'Matrix Donations: Notification email send failed',
				'A donation notification email could not be delivered by wp_mail.',
				array(
					'type'     => $type,
					'to'       => $to,
					'site_url' => home_url(),
				)
			);
		}
		return (bool) $sent;
	}
}
