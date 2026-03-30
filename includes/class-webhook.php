<?php
/**
 * Stripe webhook endpoint.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Webhook {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'password_protected_is_active', array( $this, 'allow_webhook_through_password_protected' ) );
	}

	/**
	 * Register webhook route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'matrix-donations/v1',
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Stripe webhook request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		if ( ! Matrix_Donations_Stripe_Service::load_stripe() ) {
			return new WP_Error( 'matrix_donations_no_stripe', __( 'Stripe SDK unavailable.', 'matrix-donations' ), array( 'status' => 500 ) );
		}

		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		$event     = $this->verify_event( $payload, $signature );

		if ( is_wp_error( $event ) ) {
			Matrix_Donations_Logger::log( 'error', 'Webhook signature verification failed', array( 'reason' => $event->get_error_message() ) );
			Matrix_Donations_Alerts::send(
				'Matrix Donations: Webhook signature verification failed',
				'A webhook event failed signature verification.',
				array(
					'reason'   => $event->get_error_message(),
					'site_url' => home_url(),
				)
			);
			self::set_last_webhook_status(
				array(
					'success' => false,
					'event'   => 'invalid_signature',
					'message' => $event->get_error_message(),
				)
			);
			return $event;
		}

		$event_id = sanitize_text_field( $event->id ?? '' );
		if ( Matrix_Donations_Donations_Repository::has_event_id( $event_id ) ) {
			self::set_last_webhook_status(
				array(
					'success' => true,
					'event'   => 'duplicate',
					'message' => 'Duplicate event ignored: ' . $event_id,
				)
			);
			return rest_ensure_response( array( 'received' => true, 'duplicate' => true ) );
		}

		$this->process_event( $event );
		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Verify webhook event against configured secrets.
	 *
	 * @param string $payload   Raw payload.
	 * @param string $signature Stripe-Signature header.
	 * @return \Stripe\Event|WP_Error
	 */
	private function verify_event( $payload, $signature ) {
		$secrets = Matrix_Donations_Settings::get_webhook_secrets();
		if ( empty( $signature ) || empty( $secrets ) ) {
			return new WP_Error( 'matrix_donations_webhook_invalid', __( 'Webhook signature missing or no signing secret configured.', 'matrix-donations' ), array( 'status' => 400 ) );
		}

		foreach ( $secrets as $secret ) {
			try {
				return \Stripe\Webhook::constructEvent( $payload, $signature, $secret );
			} catch ( Exception $e ) {
				continue;
			}
		}

		return new WP_Error( 'matrix_donations_webhook_invalid', __( 'Invalid webhook signature.', 'matrix-donations' ), array( 'status' => 400 ) );
	}

	/**
	 * Process supported Stripe events.
	 *
	 * @param \Stripe\Event $event Stripe event.
	 * @return void
	 */
	private function process_event( $event ) {
		$type      = sanitize_text_field( $event->type ?? '' );
		$event_id  = sanitize_text_field( $event->id ?? '' );
		$session   = $event->data->object ?? null;
		$this->process_subscription_lifecycle_event( $type, $event->data->object ?? null, $event_id, ! empty( $event->livemode ) );
		$is_session_event = in_array(
			$type,
			array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed' ),
			true
		);

		if ( ! $is_session_event || empty( $session->id ) ) {
			Matrix_Donations_Logger::log( 'info', 'Ignoring unsupported webhook event', array( 'event_type' => $type ) );
			self::set_last_webhook_status(
				array(
					'success' => true,
					'event'   => $type,
					'message' => 'Unsupported event ignored.',
				)
			);
			return;
		}

		$status = 'pending';
		if ( 'checkout.session.completed' === $type || 'checkout.session.async_payment_succeeded' === $type ) {
			$status = 'paid';
		} elseif ( 'checkout.session.async_payment_failed' === $type ) {
			$status = 'failed';
		}

		$metadata = isset( $session->metadata ) ? (array) $session->metadata : array();
		$session_id = sanitize_text_field( $session->id ?? '' );
		$existing   = Matrix_Donations_Donations_Repository::get_by_session_id( $session_id );
		$was_paid   = ! empty( $existing['status'] ) && 'paid' === sanitize_text_field( $existing['status'] );
		$donor_name = sanitize_text_field( $session->customer_details->name ?? '' );
		$name_parts = preg_split( '/\s+/', trim( $donor_name ) );
		$name_parts = is_array( $name_parts ) ? array_values( array_filter( $name_parts ) ) : array();
		$first_name = sanitize_text_field( $metadata['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $metadata['last_name'] ?? '' );
		if ( '' === $first_name && ! empty( $name_parts ) ) {
			$first_name = sanitize_text_field( (string) $name_parts[0] );
		}
		if ( '' === $last_name && count( $name_parts ) > 1 ) {
			$last_name = sanitize_text_field( implode( ' ', array_slice( $name_parts, 1 ) ) );
		}
		if ( '' === $first_name && ! empty( $existing['donor_first_name'] ) ) {
			$first_name = sanitize_text_field( (string) $existing['donor_first_name'] );
		}
		if ( '' === $last_name && ! empty( $existing['donor_last_name'] ) ) {
			$last_name = sanitize_text_field( (string) $existing['donor_last_name'] );
		}
		$donor_email = sanitize_email( $session->customer_details->email ?? '' );
		if ( '' === $donor_email && ! empty( $existing['donor_email'] ) ) {
			$donor_email = sanitize_email( (string) $existing['donor_email'] );
		}
		$metadata_type = sanitize_text_field( $metadata['donation_type'] ?? '' );
		if ( ! Matrix_Donations_Validation::is_valid_donation_type( $metadata_type ) ) {
			$metadata_type = sanitize_text_field( $existing['donation_type'] ?? '' );
		}
		if ( ! Matrix_Donations_Validation::is_valid_donation_type( $metadata_type ) ) {
			$session_mode = sanitize_text_field( $session->mode ?? '' );
			$metadata_type = ( 'subscription' === $session_mode || ! empty( $session->subscription ) ) ? 'monthly' : 'single';
		}

		$donation_data = array(
			'donation_type'            => $metadata_type,
			'donation_mode'            => ! empty( $event->livemode ) ? 'live' : 'test',
			'frequency'                => sanitize_text_field( $metadata['frequency'] ?? '' ),
			'currency'                 => sanitize_text_field( $session->currency ?? 'eur' ),
			'amount_cents'             => absint( $session->amount_total ?? 0 ),
			'status'                   => $status,
			'donor_email'              => $donor_email,
			'donor_first_name'         => $first_name,
			'donor_last_name'          => $last_name,
			'stripe_session_id'        => $session_id,
			'stripe_payment_intent_id' => sanitize_text_field( $session->payment_intent ?? '' ),
			'stripe_subscription_id'   => sanitize_text_field( $session->subscription ?? '' ),
			'stripe_event_id'          => $event_id,
			'metadata'                 => $metadata,
		);
		Matrix_Donations_Donations_Repository::upsert_by_session(
			$donation_data
		);

		if ( 'paid' === $status && ! $was_paid ) {
			Matrix_Donations_Notifications::send_success_emails( $donation_data );
		}

		Matrix_Donations_Logger::log(
			'info',
			'Webhook processed',
			array(
				'event_type' => $type,
				'event_id'   => $event_id,
				'session_id' => $session_id,
				'status'     => $status,
			)
		);
		self::set_last_webhook_status(
			array(
				'success' => true,
				'event'   => $type,
				'message' => 'Processed session ' . $session_id . ' with status ' . $status,
			)
		);
	}

	/**
	 * Process recurring subscription lifecycle events.
	 *
	 * @param string $type     Event type.
	 * @param mixed  $object   Event object.
	 * @param string $event_id Stripe event ID.
	 * @param bool   $is_live  Live mode flag.
	 * @return void
	 */
	private function process_subscription_lifecycle_event( $type, $object, $event_id, $is_live ) {
		$supported = array(
			'invoice.payment_succeeded',
			'invoice.payment_failed',
			'customer.subscription.updated',
			'customer.subscription.deleted',
		);
		if ( ! in_array( $type, $supported, true ) ) {
			return;
		}

		$subscription_id = '';
		$status          = 'pending';
		$amount_cents    = 0;
		$currency        = 'eur';
		$email           = '';

		if ( in_array( $type, array( 'invoice.payment_succeeded', 'invoice.payment_failed' ), true ) ) {
			$subscription_id = sanitize_text_field( $object->subscription ?? '' );
			$status          = ( 'invoice.payment_succeeded' === $type ) ? 'paid' : 'failed';
			$amount_cents    = absint( $object->amount_paid ?? $object->amount_due ?? 0 );
			$currency        = sanitize_text_field( $object->currency ?? 'eur' );
			$email           = sanitize_email( $object->customer_email ?? '' );
		} else {
			$subscription_id = sanitize_text_field( $object->id ?? '' );
			$sub_status      = sanitize_text_field( $object->status ?? 'pending' );
			if ( 'customer.subscription.deleted' === $type || in_array( $sub_status, array( 'canceled', 'unpaid', 'incomplete_expired' ), true ) ) {
				$status = 'failed';
			} elseif ( in_array( $sub_status, array( 'active', 'trialing' ), true ) ) {
				$status = 'paid';
			} else {
				$status = 'pending';
			}
		}

		if ( '' === $subscription_id ) {
			return;
		}

		$existing = Matrix_Donations_Donations_Repository::get_by_subscription_id( $subscription_id );
		if ( empty( $existing['id'] ) ) {
			Matrix_Donations_Logger::log(
				'info',
				'Subscription lifecycle event has no matching donation row',
				array(
					'event_type'       => $type,
					'subscription_id'  => $subscription_id,
					'event_id'         => $event_id,
				)
			);
			return;
		}

		$metadata = json_decode( (string) ( $existing['metadata'] ?? '{}' ), true );
		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}
		$metadata['subscription_lifecycle'] = array(
			'last_event_type' => $type,
			'last_event_id'   => $event_id,
			'updated_at'      => current_time( 'mysql' ),
		);

		$update = array(
			'status'          => $status,
			'donation_mode'   => $is_live ? 'live' : 'test',
			'stripe_event_id' => $event_id,
			'metadata'        => wp_json_encode( $metadata ),
		);
		$formats = array( '%s', '%s', '%s', '%s' );
		if ( $amount_cents > 0 ) {
			$update['amount_cents'] = $amount_cents;
			$formats[]              = '%d';
		}
		if ( '' !== $currency ) {
			$update['currency'] = strtolower( $currency );
			$formats[]          = '%s';
		}
		if ( '' !== $email ) {
			$update['donor_email'] = $email;
			$formats[]             = '%s';
		}

		Matrix_Donations_Donations_Repository::update_by_id( (int) $existing['id'], $update, $formats );
		Matrix_Donations_Logger::log(
			'info',
			'Subscription lifecycle event processed',
			array(
				'event_type'      => $type,
				'subscription_id' => $subscription_id,
				'status'          => $status,
				'event_id'        => $event_id,
			)
		);
		self::set_last_webhook_status(
			array(
				'success' => true,
				'event'   => $type,
				'message' => 'Processed subscription ' . $subscription_id . ' with status ' . $status,
			)
		);
	}

	/**
	 * Persist latest webhook health details.
	 *
	 * @param array $status Status payload.
	 * @return void
	 */
	private static function set_last_webhook_status( $status ) {
		$status['timestamp'] = current_time( 'mysql' );
		update_option( 'matrix_donations_last_webhook_status', $status, false );
	}

	/**
	 * Read latest webhook health details.
	 *
	 * @return array
	 */
	public static function get_last_webhook_status() {
		$value = get_option( 'matrix_donations_last_webhook_status', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Bypass Password Protected plugin only for Stripe webhook endpoint.
	 *
	 * This keeps the rest of the site protected while allowing Stripe servers
	 * to deliver webhook events without interactive login.
	 *
	 * @param bool $is_active Current Password Protected active state.
	 * @return bool
	 */
	public function allow_webhook_through_password_protected( $is_active ) {
		if ( ! $is_active ) {
			return $is_active;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( false !== strpos( $request_uri, '/wp-json/matrix-donations/v1/webhook' ) ) {
			return false;
		}

		$rest_route = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
		if ( false !== strpos( $rest_route, '/matrix-donations/v1/webhook' ) ) {
			return false;
		}

		return $is_active;
	}
}
