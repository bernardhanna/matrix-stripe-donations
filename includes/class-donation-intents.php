<?php
/**
 * Secure endpoint for checkout session creation.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Donation_Intents {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'matrix-donations/v1',
			'/checkout-intent',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_checkout_intent' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Create a Stripe Checkout Session using validated payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_checkout_intent( WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$nonce = $request->get_header( 'x-matrix-donations-nonce' );
		$token = '';
		if ( empty( $nonce ) && ! empty( $body['checkout_nonce'] ) ) {
			$nonce = sanitize_text_field( $body['checkout_nonce'] );
		}
		if ( ! empty( $body['checkout_token'] ) ) {
			$token = sanitize_text_field( $body['checkout_token'] );
		}
		$nonce_valid = wp_verify_nonce( $nonce, 'matrix_donations_checkout' );
		$token_valid = self::verify_request_token( $token );
		if ( ! $nonce_valid && ! $token_valid ) {
			return new WP_Error( 'matrix_donations_invalid_nonce', __( 'Security verification failed. Please refresh the page and try again.', 'matrix-donations' ), array( 'status' => 403 ) );
		}

		$donation_type = sanitize_text_field( $body['donation_type'] ?? '' );
		$email         = sanitize_email( $body['email'] ?? '' );
		$first_name    = sanitize_text_field( $body['first_name'] ?? '' );
		$last_name     = sanitize_text_field( $body['last_name'] ?? '' );
		$amount_value  = sanitize_text_field( $body['donation_amount'] ?? '' );
		$custom_amount = sanitize_text_field( $body['custom_amount'] ?? '' );
		$street_address = sanitize_text_field( $body['street_address'] ?? '' );
		$address_line_2 = sanitize_text_field( $body['address_line_2'] ?? '' );
		$city           = sanitize_text_field( $body['city'] ?? '' );
		$state          = sanitize_text_field( $body['state'] ?? '' );
		$postal_code    = sanitize_text_field( $body['postal_code'] ?? '' );
		$country        = strtoupper( sanitize_text_field( $body['country'] ?? '' ) );
		$is_member      = '1' === (string) ( $body['is_member'] ?? '0' ) ? '1' : '0';
		$success_url    = add_query_arg(
			'donor_first_name',
			rawurlencode( $first_name ),
			Matrix_Donations_Settings::get_success_url()
		);

		if ( ! Matrix_Donations_Validation::is_valid_donation_type( $donation_type ) ) {
			return new WP_Error( 'matrix_donations_invalid_type', __( 'Invalid donation type.', 'matrix-donations' ), array( 'status' => 422 ) );
		}
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'matrix_donations_invalid_email', __( 'Please enter a valid email address.', 'matrix-donations' ), array( 'status' => 422 ) );
		}
		if ( empty( $first_name ) || empty( $last_name ) ) {
			return new WP_Error( 'matrix_donations_missing_name', __( 'Please enter first name and surname.', 'matrix-donations' ), array( 'status' => 422 ) );
		}
		$base_amount_cents = Matrix_Donations_Validation::parse_amount_to_cents( $amount_value, $custom_amount );
		if ( null === $base_amount_cents ) {
			return new WP_Error( 'matrix_donations_invalid_amount', __( 'Please select a valid donation amount.', 'matrix-donations' ), array( 'status' => 422 ) );
		}
		$tax_amount_cents   = 0;
		$tax_rate           = Matrix_Donations_Settings::get_tax_percentage();
		if ( Matrix_Donations_Settings::is_tax_enabled() && $tax_rate > 0 ) {
			$tax_amount_cents = (int) round( $base_amount_cents * ( $tax_rate / 100 ) );
		}
		$amount_cents = $base_amount_cents + $tax_amount_cents;
		$idempotency_key = $this->build_idempotency_key(
			array(
				'donation_type' => $donation_type,
				'amount_cents'  => $amount_cents,
				'email'         => $email,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'mode'          => Matrix_Donations_Settings::get_mode(),
			)
		);

		$cached_intent = get_transient( 'matrix_donations_intent_' . $idempotency_key );
		if ( is_array( $cached_intent ) && ! empty( $cached_intent['checkoutUrl'] ) ) {
			return rest_ensure_response(
				array(
					'success'     => true,
					'checkoutUrl' => esc_url_raw( $cached_intent['checkoutUrl'] ),
					'sessionId'   => sanitize_text_field( (string) ( $cached_intent['sessionId'] ?? '' ) ),
					'cached'      => true,
				)
			);
		}

		if ( Matrix_Donations_Settings::is_mock_checkout_enabled() ) {
			$mock_session_id = 'mock_' . wp_generate_password( 12, false, false );
			$mock_url        = str_replace( '{CHECKOUT_SESSION_ID}', rawurlencode( $mock_session_id ), $success_url );
			$donation_data   = array(
				'donation_type'     => $donation_type,
				'donation_mode'     => 'mock',
				'currency'          => 'eur',
				'amount_cents'      => $amount_cents,
				'status'            => 'paid',
				'donor_email'       => $email,
				'donor_first_name'  => $first_name,
				'donor_last_name'   => $last_name,
				'stripe_session_id' => $mock_session_id,
				'metadata'          => array(
					'source'            => 'mock_checkout',
					'base_amount_cents' => $base_amount_cents,
					'tax_amount_cents'  => $tax_amount_cents,
					'tax_rate'          => $tax_rate,
					'street_address'    => $street_address,
					'address_line_2'    => $address_line_2,
					'city'              => $city,
					'state'             => $state,
					'postal_code'       => $postal_code,
					'country'           => $country,
					'is_member'         => $is_member,
				),
			);

			Matrix_Donations_Donations_Repository::insert_pending_donation(
				$donation_data
			);
			Matrix_Donations_Notifications::send_success_emails( $donation_data );

			Matrix_Donations_Logger::log(
				'info',
				'Mock checkout session generated',
				array(
					'session_id'    => $mock_session_id,
					'donation_type' => $donation_type,
					'amount_cents'  => $amount_cents,
					'tax_amount'    => $tax_amount_cents,
					'tax_rate'      => $tax_rate,
				)
			);

			return rest_ensure_response(
				array(
					'success'     => true,
					'checkoutUrl' => $mock_url,
					'sessionId'   => $mock_session_id,
					'mock'        => true,
				)
			);
		}

		try {
			$session = Matrix_Donations_Stripe_Service::create_checkout_session(
				array(
					'donation_type' => $donation_type,
					'amount_cents'  => $amount_cents,
					'currency'      => 'eur',
					'email'         => $email,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'success_url'   => $success_url,
					'cancel_url'    => Matrix_Donations_Settings::get_cancel_url(),
					'idempotency_key' => $idempotency_key,
					'metadata'      => array(
						'base_amount_cents' => $base_amount_cents,
						'tax_amount_cents'  => $tax_amount_cents,
						'tax_rate'          => $tax_rate,
						'street_address'    => $street_address,
						'address_line_2'    => $address_line_2,
						'city'              => $city,
						'state'             => $state,
						'postal_code'       => $postal_code,
						'country'           => $country,
						'is_member'         => $is_member,
					),
				)
			);
		} catch ( Exception $e ) {
			$user_message = __( 'Unable to start checkout right now. Please verify Stripe mode and secret key in Matrix Donations settings.', 'matrix-donations' );
			Matrix_Donations_Logger::log(
				'error',
				'Checkout session creation failed',
				array(
					'error'         => $e->getMessage(),
					'donation_type' => $donation_type,
				)
			);
			Matrix_Donations_Alerts::send(
				'Matrix Donations: Checkout session creation failed',
				'Stripe checkout session could not be created.',
				array(
					'donation_type' => $donation_type,
					'error'         => sanitize_text_field( $e->getMessage() ),
					'mode'          => Matrix_Donations_Settings::get_mode(),
					'site_url'      => home_url(),
				)
			);
			$user_message = sprintf(
				/* translators: %s: internal checkout exception message */
				__( 'Unable to start checkout: %s', 'matrix-donations' ),
				sanitize_text_field( $e->getMessage() )
			);
			return new WP_Error( 'matrix_donations_checkout_error', $user_message, array( 'status' => 500 ) );
		}

		Matrix_Donations_Donations_Repository::insert_pending_donation(
			array(
				'donation_type'    => $donation_type,
				'donation_mode'    => Matrix_Donations_Settings::get_mode(),
				'currency'         => 'eur',
				'amount_cents'     => $amount_cents,
				'status'           => 'pending',
				'donor_email'      => $email,
				'donor_first_name' => $first_name,
				'donor_last_name'  => $last_name,
				'stripe_session_id'=> $session->id ?? '',
				'metadata'         => array(
					'source'            => 'intent_endpoint',
					'base_amount_cents' => $base_amount_cents,
					'tax_amount_cents'  => $tax_amount_cents,
					'tax_rate'          => $tax_rate,
					'street_address'    => $street_address,
					'address_line_2'    => $address_line_2,
					'city'              => $city,
					'state'             => $state,
					'postal_code'       => $postal_code,
					'country'           => $country,
					'is_member'         => $is_member,
				),
			)
		);
		set_transient(
			'matrix_donations_intent_' . $idempotency_key,
			array(
				'checkoutUrl' => $session->url,
				'sessionId'   => $session->id,
			),
			15 * MINUTE_IN_SECONDS
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'checkoutUrl' => $session->url,
				'sessionId'   => $session->id,
			)
		);
	}

	/**
	 * Build deterministic idempotency key for duplicate form submissions.
	 *
	 * @param array $seed Seed values.
	 * @return string
	 */
	private function build_idempotency_key( $seed ) {
		$normalized = wp_json_encode( $seed );
		$raw        = hash( 'sha256', (string) $normalized );
		return 'mdi_' . substr( $raw, 0, 42 );
	}

	/**
	 * Build signed fallback token for checkout requests.
	 *
	 * @return string
	 */
	public static function build_request_token() {
		$tick = self::get_token_tick();
		return hash_hmac( 'sha256', 'matrix_donations_checkout|' . $tick, wp_salt( 'nonce' ) );
	}

	/**
	 * Validate signed fallback token for current and previous window.
	 *
	 * @param string $token Token from request.
	 * @return bool
	 */
	private static function verify_request_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}
		$current_tick = self::get_token_tick();
		$valid_tokens = array(
			hash_hmac( 'sha256', 'matrix_donations_checkout|' . $current_tick, wp_salt( 'nonce' ) ),
			hash_hmac( 'sha256', 'matrix_donations_checkout|' . ( $current_tick - 1 ), wp_salt( 'nonce' ) ),
		);
		foreach ( $valid_tokens as $valid_token ) {
			if ( hash_equals( $valid_token, $token ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 12-hour tick window for fallback token validity.
	 *
	 * @return int
	 */
	private static function get_token_tick() {
		return (int) floor( time() / ( 12 * HOUR_IN_SECONDS ) );
	}
}
