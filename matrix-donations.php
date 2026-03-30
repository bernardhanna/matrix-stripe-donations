<?php
/**
 * Plugin Name: Matrix Donations
 * Plugin URI: https://diabetes.ie
 * Description: Donation forms with Stripe checkout and Salesforce integration. Includes single, monthly, and membership donation types.
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Diabetes Ireland
 * Text Domain: matrix-donations
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MATRIX_DONATIONS_VERSION', '1.0.1' );
define( 'MATRIX_DONATIONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MATRIX_DONATIONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Matrix_Donations {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies() {
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-checkout.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-validation.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-logger.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-alerts.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-donations-repository.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-notifications.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-stripe-service.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-donation-intents.php';
		require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-webhook.php';
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'donation_flow_body_class' ) );
		add_action( 'matrix_donations_header', array( $this, 'render_donation_steps_header' ) );
	}

	public function init() {
		Matrix_Donations_Settings::instance();
		Matrix_Donations_Shortcode::instance();
		Matrix_Donations_Checkout::instance();
		Matrix_Donations_Donation_Intents::instance();
		Matrix_Donations_Webhook::instance();
		$this->ensure_system_pages_have_shortcodes();
	}

	public function enqueue_assets() {
		// Enqueue on singular pages (donation forms are typically on pages)
		if ( ! is_singular() ) {
			return;
		}

		wp_enqueue_script(
			'matrix-donations-stripe',
			'https://js.stripe.com/v3/',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'matrix-donations',
			MATRIX_DONATIONS_PLUGIN_URL . 'assets/js/donation-forms.js',
			array( 'jquery' ),
			filemtime( MATRIX_DONATIONS_PLUGIN_DIR . 'assets/js/donation-forms.js' ),
			true
		);

		wp_enqueue_style(
			'matrix-donations',
			MATRIX_DONATIONS_PLUGIN_URL . 'assets/css/donation-forms.css',
			array(),
			filemtime( MATRIX_DONATIONS_PLUGIN_DIR . 'assets/css/donation-forms.css' )
		);
		if ( class_exists( 'Matrix_Donations_Settings' ) ) {
			$inline_css = Matrix_Donations_Settings::get_design_inline_css();
			if ( ! empty( $inline_css ) ) {
				wp_add_inline_style( 'matrix-donations', $inline_css );
			}
		}

		$donation_page_ids = Matrix_Donations_Settings::get_donation_page_ids();
		$urls = array();
		foreach ( array( 'single', 'monthly', 'membership', 'renew_membership' ) as $key ) {
			$pid = $donation_page_ids[ $key ] ?? 0;
			$urls[ 'donation_' . $key ] = $pid ? get_permalink( $pid ) : '';
		}
		wp_localize_script( 'matrix-donations', 'matrixDonationsData', array(
			'url'                   => home_url(),
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'donation_urls'         => $urls,
			'checkout_intent_url'   => rest_url( 'matrix-donations/v1/checkout-intent' ),
			'checkout_intent_nonce' => wp_create_nonce( 'matrix_donations_checkout' ),
			'checkout_intent_token' => class_exists( 'Matrix_Donations_Donation_Intents' ) ? Matrix_Donations_Donation_Intents::build_request_token() : '',
			'tax_enabled'           => class_exists( 'Matrix_Donations_Settings' ) && Matrix_Donations_Settings::is_tax_enabled() ? 1 : 0,
			'tax_rate'              => class_exists( 'Matrix_Donations_Settings' ) ? Matrix_Donations_Settings::get_tax_percentage() : 0,
		) );
	}

	public function get_template_path( $name ) {
		return MATRIX_DONATIONS_PLUGIN_DIR . 'templates/' . $name . '.php';
	}

	/**
	 * Ensure required system pages contain expected shortcodes.
	 *
	 * @return void
	 */
	private function ensure_system_pages_have_shortcodes() {
		$success_page_id = Matrix_Donations_Settings::get_success_page_id();
		if ( $success_page_id ) {
			$this->ensure_page_shortcode_if_empty( $success_page_id, '[matrix_donations_thank_you]' );
		}
	}

	/**
	 * Populate page content with shortcode when currently empty.
	 *
	 * @param int    $page_id   WordPress page ID.
	 * @param string $shortcode Shortcode string.
	 * @return void
	 */
	private function ensure_page_shortcode_if_empty( $page_id, $shortcode ) {
		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}
		if ( false !== strpos( (string) $post->post_content, $shortcode ) ) {
			return;
		}
		if ( '' !== trim( (string) $post->post_content ) ) {
			return;
		}
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $shortcode,
			)
		);
	}

	public function get_template( $name, $args = array() ) {
		$path = $this->get_template_path( $name );
		if ( file_exists( $path ) ) {
			extract( $args );
			include $path;
		}
	}

	/**
	 * Load the donate_box template (for ACF/flex layouts).
	 * Theme can call: matrix_donations()->render_donate_box();
	 */
	public function render_donate_box() {
		$this->get_template( 'donate-box' );
	}

	/**
	 * Get donation page permalink by type.
	 *
	 * @param string $type single, monthly, membership, renew_membership
	 * @return string
	 */
	public static function get_donation_permalink( $type ) {
		if ( ! class_exists( 'Matrix_Donations_Settings' ) ) {
			return '';
		}
		$ids = Matrix_Donations_Settings::get_donation_page_ids();
		$page_id = $ids[ $type ] ?? 0;
		return $page_id ? get_permalink( $page_id ) : '';
	}

	/**
	 * Whether the current page is part of the donation flow.
	 *
	 * @return bool
	 */
	public static function is_donation_flow() {
		return self::get_donation_flow_step() > 0;
	}

	/**
	 * Get current step in the donation flow:
	 * 0 = not in flow, 1 = donation form.
	 *
	 * @return int 0 or 1
	 */
	public static function get_donation_flow_step() {
		if ( ! is_singular( 'page' ) || ! class_exists( 'Matrix_Donations_Settings' ) ) {
			return 0;
		}
		$page_id    = get_queried_object_id();
		$opts       = Matrix_Donations_Settings::get_options();
		$single     = ! empty( $opts['page_single'] ) ? (int) $opts['page_single'] : 0;
		$monthly    = ! empty( $opts['page_monthly'] ) ? (int) $opts['page_monthly'] : 0;
		if ( ( $single && $page_id === $single ) || ( $monthly && $page_id === $monthly ) ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Add body classes for donation flow pages.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function donation_flow_body_class( $classes ) {
		$step = self::get_donation_flow_step();
		if ( $step === 0 ) {
			return $classes;
		}
		$classes[] = 'matrix-donation-flow';
		$classes[] = 'matrix-donation-step-' . $step;
		return $classes;
	}

	/**
	 * Output the multi-step donation header (logo + progress). Fired by do_action( 'matrix_donations_header' ).
	 */
	public function render_donation_steps_header() {
		$this->get_template( 'header-donation-steps' );
	}
}

function matrix_donations() {
	return Matrix_Donations::instance();
}

/**
 * Whether the current page is part of the Matrix Donations flow.
 * Theme can use this to swap the navbar for the donation header.
 *
 * @return bool
 */
function matrix_donations_is_donation_flow() {
	return class_exists( 'Matrix_Donations' ) && Matrix_Donations::is_donation_flow();
}

add_action( 'plugins_loaded', 'matrix_donations', 5 );

/**
 * Activation: create required pages if they don't exist.
 */
register_activation_hook( __FILE__, 'matrix_donations_activate' );
function matrix_donations_activate() {
	require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-settings.php';
	require_once MATRIX_DONATIONS_PLUGIN_DIR . 'includes/class-donations-repository.php';
	$opts = get_option( 'matrix_donations_options', array() );
	$defaults = Matrix_Donations_Settings::get_options();
	$opts = wp_parse_args( $opts, $defaults );

	$pages = array(
		'success'          => array( 'title' => 'Donation Success', 'slug' => 'success', 'content' => '[matrix_donations_thank_you]', 'option' => 'success_page_id' ),
		'cancel'           => array( 'title' => 'Donation Cancelled', 'slug' => 'cancel', 'content' => '', 'option' => 'cancel_page_id' ),
		'single'           => array( 'title' => 'Donate', 'slug' => 'donate', 'content' => '[donate type="single"]', 'option' => 'page_single' ),
		'monthly'          => array( 'title' => 'Donate Monthly', 'slug' => 'donate-monthly', 'content' => '[donate type="monthly"]', 'option' => 'page_monthly' ),
		'membership'      => array( 'title' => 'Membership', 'slug' => 'membership', 'content' => '[donate type="membership"]', 'option' => 'page_membership' ),
		'renew_membership' => array( 'title' => 'Renew Membership', 'slug' => 'renew-membership', 'content' => '[donate type="renew_membership"]', 'option' => 'page_renew_membership' ),
	);

	foreach ( $pages as $config ) {
		$existing_id = isset( $opts[ $config['option'] ] ) ? absint( $opts[ $config['option'] ] ) : 0;
		if ( $existing_id && get_post_status( $existing_id ) === 'publish' ) {
			continue;
		}
		$existing = get_page_by_path( $config['slug'] );
		if ( $existing ) {
			$opts[ $config['option'] ] = $existing->ID;
			continue;
		}
		$author = get_current_user_id();
		if ( ! $author ) {
			$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
			$author = $admin ? $admin[0]->ID : 1;
		}
		$page_id = wp_insert_post( array(
			'post_title'   => $config['title'],
			'post_name'    => $config['slug'],
			'post_content' => $config['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => $author,
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$opts[ $config['option'] ] = $page_id;
		}
	}

	Matrix_Donations_Donations_Repository::create_table();
	update_option( 'matrix_donations_options', $opts );
}
