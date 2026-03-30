<?php
/**
 * Matrix Donations Shortcode
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Shortcode {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'donate', array( $this, 'render_shortcode' ) );
		add_shortcode( 'donate_box', array( $this, 'render_donate_box_shortcode' ) );
		add_shortcode( 'matrix_donations_thank_you', array( $this, 'render_thank_you_shortcode' ) );
	}

	public function render_donate_box_shortcode( $atts ) {
		ob_start();
		matrix_donations()->render_donate_box();
		return ob_get_clean();
	}

	public function render_thank_you_shortcode( $atts ) {
		ob_start();
		matrix_donations()->get_template( 'thank-you' );
		return ob_get_clean();
	}

	public function render_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'type' => 'single',
		), $atts, 'donate' );

		ob_start();
		matrix_donations()->get_template( 'donate-main', array(
			'type' => $a['type'],
		) );
		return ob_get_clean();
	}
}
