<?php
/**
 * Matrix Donations Checkout Handler
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Checkout {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'the_content', array( $this, 'render_checkout_content' ) );
		add_filter( 'body_class', array( $this, 'add_thank_you_body_class' ) );
	}

	/**
	 * Render thank-you template on configured success page.
	 *
	 * @param string $content Existing content.
	 * @return string
	 */
	public function render_checkout_content( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$success_page_id  = Matrix_Donations_Settings::get_success_page_id();

		if ( $success_page_id && is_page( $success_page_id ) ) {
			$args = $this->get_success_page_args();
			ob_start();
			matrix_donations()->get_template( 'thank-you', $args );
			return ob_get_clean();
		}

		return $content;
	}

	/**
	 * Add dedicated body class on thank-you page.
	 *
	 * @param array $classes Existing classes.
	 * @return array
	 */
	public function add_thank_you_body_class( $classes ) {
		if ( is_admin() ) {
			return $classes;
		}

		$success_page_id = Matrix_Donations_Settings::get_success_page_id();
		if ( $success_page_id && is_page( $success_page_id ) ) {
			$classes[] = 'matrix-thank-you-page';
		}
		return $classes;
	}

	/**
	 * Build thank-you page args from session query.
	 *
	 * @return array
	 */
	private function get_success_page_args() {
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$donation   = Matrix_Donations_Donations_Repository::get_by_session_id( $session_id );

		return array(
			'session_id'       => $session_id,
			'donation_record'  => is_array( $donation ) ? $donation : array(),
		);
	}
}
