<?php
/**
 * Stripe API service wrapper.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Stripe_Service {

	/**
	 * Load Stripe SDK from plugin/theme vendor.
	 *
	 * @return bool
	 */
	public static function load_stripe() {
		$plugin_vendor = MATRIX_DONATIONS_PLUGIN_DIR . 'vendor/autoload.php';
		$theme_vendor  = get_template_directory() . '/vendor/autoload.php';

		if ( file_exists( $plugin_vendor ) ) {
			require_once $plugin_vendor;
			return true;
		}
		if ( file_exists( $theme_vendor ) ) {
			require_once $theme_vendor;
			return true;
		}
		return false;
	}

	/**
	 * Create Stripe Checkout session.
	 *
	 * @param array $args Session data.
	 * @return \Stripe\Checkout\Session
	 * @throws Exception When Stripe cannot be initialized.
	 */
	public static function create_checkout_session( $args ) {
		if ( ! self::load_stripe() ) {
			throw new Exception( 'Stripe PHP library not found. Run composer install in matrix-donations.' );
		}

		$secret_key = Matrix_Donations_Settings::get_active_stripe_secret_key();
		if ( empty( $secret_key ) ) {
			throw new Exception( 'Stripe secret key is missing for the active mode.' );
		}
		self::validate_secret_key_for_mode( $secret_key, Matrix_Donations_Settings::get_mode() );

		\Stripe\Stripe::setApiKey( $secret_key );

		$donation_type = $args['donation_type'];
		$amount_cents  = (int) $args['amount_cents'];
		$currency      = strtolower( $args['currency'] ?? 'eur' );
		$email         = $args['email'];
		$first_name    = $args['first_name'];
		$last_name     = $args['last_name'];
		$full_name     = trim( $first_name . ' ' . $last_name );
		$extra_metadata = isset( $args['metadata'] ) && is_array( $args['metadata'] ) ? $args['metadata'] : array();

		$base_payload = array(
			'customer_email' => $email,
			'success_url'    => $args['success_url'],
			'cancel_url'     => $args['cancel_url'],
			'line_items'     => array(
				array(
					'price_data' => array(
						'currency'     => $currency,
						'product_data' => array(
							'name' => ( 'monthly' === $donation_type )
								? __( 'Monthly Donation', 'matrix-donations' )
								: __( 'Single Donation', 'matrix-donations' ),
						),
						'unit_amount'  => $amount_cents,
					),
					'quantity'   => 1,
				),
			),
			'metadata'       => array(
				'donation_type' => $donation_type,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'full_name'     => $full_name,
				'site_url'      => home_url(),
			),
		);
		$base_payload['metadata'] = array_merge( $base_payload['metadata'], $extra_metadata );

		if ( 'monthly' === $donation_type ) {
			$base_payload['mode']                                      = 'subscription';
			$base_payload['line_items'][0]['price_data']['recurring'] = array( 'interval' => 'month' );
		} else {
			$base_payload['mode']                = 'payment';
			$base_payload['payment_intent_data'] = array(
				'metadata' => array(
					'donation_type' => $donation_type,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'full_name'     => $full_name,
				),
			);
			$base_payload['payment_intent_data']['metadata'] = array_merge( $base_payload['payment_intent_data']['metadata'], $extra_metadata );
		}

		$request_options = array();
		if ( ! empty( $args['idempotency_key'] ) ) {
			$request_options['idempotency_key'] = sanitize_text_field( (string) $args['idempotency_key'] );
		}

		if ( ! empty( $request_options ) ) {
			return \Stripe\Checkout\Session::create( $base_payload, $request_options );
		}
		return \Stripe\Checkout\Session::create( $base_payload );
	}

	/**
	 * Ensure mode and secret key prefix match.
	 *
	 * @param string $secret_key Secret key.
	 * @param string $mode       Active mode.
	 * @return void
	 * @throws Exception When key does not match mode.
	 */
	private static function validate_secret_key_for_mode( $secret_key, $mode ) {
		$is_test_key = 0 === strpos( $secret_key, 'sk_test_' ) || 0 === strpos( $secret_key, 'rk_test_' );
		$is_live_key = 0 === strpos( $secret_key, 'sk_live_' ) || 0 === strpos( $secret_key, 'rk_live_' );

		if ( ! $is_test_key && ! $is_live_key ) {
			throw new Exception( 'Stripe key format is invalid. Use a Secret or Restricted Key that starts with sk_test_, sk_live_, rk_test_, or rk_live_.' );
		}

		if ( 'test' === $mode && ! $is_test_key ) {
			throw new Exception( 'Stripe Mode is Sandbox, but a live key is active. Please use a sk_test_ or rk_test_ key in Sandbox mode.' );
		}

		if ( 'live' === $mode && ! $is_live_key ) {
			throw new Exception( 'Stripe Mode is Live, but a test key is active. Please use a sk_live_ or rk_live_ key in Live mode.' );
		}
	}
}
