<?php
/**
 * Validation helpers for donation checkout payloads.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Validation {

	/**
	 * Allowed donation types for secure Stripe flow.
	 *
	 * @return string[]
	 */
	public static function allowed_donation_types() {
		return array( 'single', 'monthly' );
	}

	/**
	 * Validate donation type.
	 *
	 * @param string $donation_type Donation type.
	 * @return bool
	 */
	public static function is_valid_donation_type( $donation_type ) {
		return in_array( $donation_type, self::allowed_donation_types(), true );
	}

	/**
	 * Convert user-selected amount into integer cents.
	 *
	 * @param mixed $donation_amount Preset amount or "custom".
	 * @param mixed $custom_amount   Custom amount (optional).
	 * @return int|null
	 */
	public static function parse_amount_to_cents( $donation_amount, $custom_amount = null ) {
		$allowed_presets = array( 10, 20, 100, 250 );
		$min_amount      = 1;
		$max_amount      = 100000;

		if ( 'custom' === $donation_amount ) {
			$amount = is_numeric( $custom_amount ) ? (float) $custom_amount : 0.0;
		} else {
			$amount = is_numeric( $donation_amount ) ? (float) $donation_amount : 0.0;
			if ( ! in_array( (int) round( $amount ), $allowed_presets, true ) ) {
				return null;
			}
		}

		if ( $amount < $min_amount || $amount > $max_amount ) {
			return null;
		}

		return (int) round( $amount * 100 );
	}
}
