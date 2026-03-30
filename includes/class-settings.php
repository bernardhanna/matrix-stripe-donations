<?php
/**
 * Matrix Donations Settings and admin pages.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Settings {

	const OPTION_GROUP = 'matrix_donations_settings';
	const OPTION_NAME  = 'matrix_donations_options';

	private static $instance = null;
	private $settings_page_hook = '';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_post_matrix_donations_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_matrix_donations_send_test_emails', array( $this, 'handle_send_test_emails' ) );
		add_action( 'admin_post_matrix_donations_validate_stripe', array( $this, 'handle_validate_stripe' ) );
		add_action( 'admin_post_matrix_donations_run_quick_diagnostics', array( $this, 'handle_run_quick_diagnostics' ) );
		add_action( 'admin_post_matrix_donations_run_smoke_tests', array( $this, 'handle_run_smoke_tests' ) );
		add_action( 'admin_post_matrix_donations_run_full_e2e', array( $this, 'handle_run_full_e2e' ) );
		add_action( 'admin_post_matrix_donations_mark_e2e_status', array( $this, 'handle_mark_e2e_status' ) );
		add_action( 'admin_notices', array( $this, 'render_mock_mode_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_test_email_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_stripe_validation_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_quick_diagnostics_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_smoke_tests_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_full_e2e_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_e2e_status_notice' ) );
	}

	public function add_menu_page() {
		add_menu_page(
			__( 'Matrix Donations', 'matrix-donations' ),
			__( 'Matrix Donations', 'matrix-donations' ),
			'manage_options',
			'matrix-donations',
			array( $this, 'render_donations_page' ),
			'dashicons-heart',
			57
		);

		add_submenu_page(
			'matrix-donations',
			__( 'Donations', 'matrix-donations' ),
			__( 'Donations', 'matrix-donations' ),
			'manage_options',
			'matrix-donations',
			array( $this, 'render_donations_page' )
		);

		$this->settings_page_hook = add_submenu_page(
			'matrix-donations',
			__( 'Settings', 'matrix-donations' ),
			__( 'Settings', 'matrix-donations' ),
			'manage_options',
			'matrix-donations-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'matrix-donations',
			__( 'Logs', 'matrix-donations' ),
			__( 'Logs', 'matrix-donations' ),
			'manage_options',
			'matrix-donations-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'matrix-donations',
			__( 'Setup Guide', 'matrix-donations' ),
			__( 'Setup Guide', 'matrix-donations' ),
			'manage_options',
			'matrix-donations-setup-guide',
			array( $this, 'render_setup_guide_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		add_settings_section(
			'matrix_donations_api',
			__( 'Stripe', 'matrix-donations' ),
			array( $this, 'section_api_callback' ),
			'matrix-donations-settings'
		);

		add_settings_section(
			'matrix_donations_tax',
			__( 'Tax', 'matrix-donations' ),
			array( $this, 'section_tax_callback' ),
			'matrix-donations-settings'
		);

		add_settings_section(
			'matrix_donations_testing',
			__( 'Testing & Diagnostics', 'matrix-donations' ),
			array( $this, 'section_testing_callback' ),
			'matrix-donations-settings'
		);

		add_settings_field(
			'stripe_mode',
			__( 'Stripe Mode', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_api',
			array(
				'field'   => 'stripe_mode',
				'options' => array(
					'test' => __( 'Sandbox (Test)', 'matrix-donations' ),
					'live' => __( 'Live', 'matrix-donations' ),
				),
			)
		);

		$api_fields = array(
			'stripe_test_secret_key'      => array( __( 'Stripe Test Secret Key', 'matrix-donations' ), 'password' ),
			'stripe_live_secret_key'      => array( __( 'Stripe Live Secret Key', 'matrix-donations' ), 'password' ),
			'stripe_test_publishable_key' => array( __( 'Stripe Test Publishable Key', 'matrix-donations' ), 'text' ),
			'stripe_live_publishable_key' => array( __( 'Stripe Live Publishable Key', 'matrix-donations' ), 'text' ),
			'stripe_test_webhook_secret'  => array( __( 'Stripe Test Webhook Secret', 'matrix-donations' ), 'password' ),
			'stripe_live_webhook_secret'  => array( __( 'Stripe Live Webhook Secret', 'matrix-donations' ), 'password' ),
		);

		foreach ( $api_fields as $field => $meta ) {
			add_settings_field(
				$field,
				$meta[0],
				array( $this, 'field_text_callback' ),
				'matrix-donations-settings',
				'matrix_donations_api',
				array(
					'field' => $field,
					'type'  => $meta[1],
				)
			);
		}

		add_settings_field(
			'debug_enabled',
			__( 'Debug Logging', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field'   => 'debug_enabled',
				'options' => array(
					'0' => __( 'Disabled', 'matrix-donations' ),
					'1' => __( 'Enabled', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'mock_checkout_enabled',
			__( 'Mock Checkout', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field'   => 'mock_checkout_enabled',
				'options' => array(
					'0' => __( 'Disabled', 'matrix-donations' ),
					'1' => __( 'Enabled (development only)', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'checkout_tax_enabled',
			__( 'Apply Tax', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_tax',
			array(
				'field'   => 'checkout_tax_enabled',
				'options' => array(
					'0' => __( 'No tax', 'matrix-donations' ),
					'1' => __( 'Add tax to checkout total', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'checkout_tax_percentage',
			__( 'Tax Percentage', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_tax',
			array(
				'field' => 'checkout_tax_percentage',
				'type'  => 'number',
				'step'  => '0.01',
				'min'   => '0',
				'max'   => '100',
			)
		);

		add_settings_field(
			'technical_alerts_enabled',
			__( 'Technical Alerts', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field'   => 'technical_alerts_enabled',
				'options' => array(
					'0' => __( 'Disabled', 'matrix-donations' ),
					'1' => __( 'Enabled', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'technical_alert_emails',
			__( 'Technical Alert Emails', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array( 'field' => 'technical_alert_emails' )
		);

		add_settings_field(
			'testing_cards_enabled',
			__( 'Show Test Card Helper', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field'   => 'testing_cards_enabled',
				'options' => array(
					'1' => __( 'Enabled', 'matrix-donations' ),
					'0' => __( 'Disabled', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'testing_cards_reference',
			__( 'Test Card Reference Text', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field' => 'testing_cards_reference',
				'rows'  => 5,
			)
		);

		add_settings_field(
			'e2e_github_repository',
			__( 'E2E GitHub Repository', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field'       => 'e2e_github_repository',
				'placeholder' => 'owner/repo',
			)
		);

		add_settings_field(
			'e2e_github_workflow',
			__( 'E2E Workflow File', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field' => 'e2e_github_workflow',
			)
		);

		add_settings_field(
			'e2e_github_ref',
			__( 'E2E Branch/Ref', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field' => 'e2e_github_ref',
			)
		);

		add_settings_field(
			'e2e_github_token',
			__( 'E2E GitHub Token', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_testing',
			array(
				'field' => 'e2e_github_token',
				'type'  => 'password',
			)
		);

		add_settings_section(
			'matrix_donations_pages',
			__( 'Donation Pages', 'matrix-donations' ),
			array( $this, 'section_pages_callback' ),
			'matrix-donations-settings'
		);

		$page_fields = array(
			'page_single'           => __( 'Single Donation Page', 'matrix-donations' ),
			'page_monthly'          => __( 'Monthly Donation Page', 'matrix-donations' ),
			'page_membership'       => __( 'Membership Page', 'matrix-donations' ),
			'page_renew_membership' => __( 'Renew Membership Page', 'matrix-donations' ),
			'checkout_page_id'      => __( 'Checkout Page', 'matrix-donations' ),
			'success_page_id'       => __( 'Success Page', 'matrix-donations' ),
			'cancel_page_id'        => __( 'Cancel Page', 'matrix-donations' ),
		);

		foreach ( $page_fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'field_page_select_callback' ),
				'matrix-donations-settings',
				'matrix_donations_pages',
				array( 'field' => $field )
			);
		}

		add_settings_section(
			'matrix_donations_content',
			__( 'Donation Content', 'matrix-donations' ),
			array( $this, 'section_content_callback' ),
			'matrix-donations-settings'
		);

		add_settings_field(
			'content_heading',
			__( 'Heading', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_heading' )
		);

		add_settings_field(
			'content_description',
			__( 'Description', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_description' )
		);

		add_settings_field(
			'content_details',
			__( 'Details', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_details' )
		);

		add_settings_field(
			'content_info',
			__( 'Info Text', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_info' )
		);

		add_settings_field(
			'content_see_more_url',
			__( 'See More Link URL', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array(
				'field' => 'content_see_more_url',
				'type'  => 'url',
			)
		);

		add_settings_field(
			'content_see_more_title',
			__( 'See More Link Text', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_see_more_title' )
		);

		add_settings_field(
			'content_see_more_target',
			__( 'See More Link Target', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array(
				'field'   => 'content_see_more_target',
				'options' => array(
					''       => __( 'Same window', 'matrix-donations' ),
					'_blank' => __( 'New tab', 'matrix-donations' ),
				),
			)
		);

		add_settings_field(
			'content_feature_image',
			__( 'Feature Image', 'matrix-donations' ),
			array( $this, 'field_media_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_feature_image' )
		);

		add_settings_field(
			'content_banner',
			__( 'Donation Form Hero Image', 'matrix-donations' ),
			array( $this, 'field_media_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_banner' )
		);

		add_settings_field(
			'content_form_info_message',
			__( 'Donation Form Info Message', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array(
				'field' => 'content_form_info_message',
				'rows'  => 2,
			)
		);

		add_settings_field(
			'content_form_impact_message',
			__( 'Donation Form Impact Message', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array( 'field' => 'content_form_impact_message' )
		);

		add_settings_field(
			'content_form_impact_enabled',
			__( 'Show Donation Impact Message', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_content',
			array(
				'field'   => 'content_form_impact_enabled',
				'options' => array(
					'1' => __( 'Enabled', 'matrix-donations' ),
					'0' => __( 'Disabled', 'matrix-donations' ),
				),
			)
		);

		add_settings_section(
			'matrix_donations_design',
			__( 'Design & Accessibility', 'matrix-donations' ),
			array( $this, 'section_design_callback' ),
			'matrix-donations-settings'
		);

		add_settings_field(
			'design_enable_custom_styles',
			__( 'Enable Custom Style Overrides', 'matrix-donations' ),
			array( $this, 'field_select_callback' ),
			'matrix-donations-settings',
			'matrix_donations_design',
			array(
				'field'   => 'design_enable_custom_styles',
				'options' => array(
					'0' => __( 'Disabled (use default design)', 'matrix-donations' ),
					'1' => __( 'Enabled', 'matrix-donations' ),
				),
			)
		);

		$design_color_fields = array(
			'design_button_bg'             => __( 'Primary Button Background', 'matrix-donations' ),
			'design_button_text'           => __( 'Primary Button Text', 'matrix-donations' ),
			'design_button_border'         => __( 'Primary Button Border', 'matrix-donations' ),
			'design_button_hover_bg'       => __( 'Primary Button Hover Background', 'matrix-donations' ),
			'design_button_hover_text'     => __( 'Primary Button Hover Text', 'matrix-donations' ),
			'design_button_hover_border'   => __( 'Primary Button Hover Border', 'matrix-donations' ),
			'design_button_active_bg'      => __( 'Primary Button Pressed Background', 'matrix-donations' ),
			'design_button_active_text'    => __( 'Primary Button Pressed Text', 'matrix-donations' ),
			'design_button_focus_ring'     => __( 'Primary Button Focus Ring', 'matrix-donations' ),
			'design_thank_you_primary_bg'  => __( 'Thank You Primary Button Background', 'matrix-donations' ),
			'design_thank_you_primary_text'=> __( 'Thank You Primary Button Text', 'matrix-donations' ),
			'design_thank_you_secondary_bg'=> __( 'Thank You Secondary Button Background', 'matrix-donations' ),
			'design_thank_you_secondary_text' => __( 'Thank You Secondary Button Text', 'matrix-donations' ),
			'design_thank_you_secondary_border' => __( 'Thank You Secondary Button Border', 'matrix-donations' ),
		);
		foreach ( $design_color_fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'field_color_callback' ),
				'matrix-donations-settings',
				'matrix_donations_design',
				array( 'field' => $field )
			);
		}

		add_settings_field(
			'design_custom_css',
			__( 'Custom CSS (Optional)', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_design',
			array(
				'field' => 'design_custom_css',
				'rows'  => 8,
			)
		);

		add_settings_section(
			'matrix_donations_emails',
			__( 'Donation Emails', 'matrix-donations' ),
			array( $this, 'section_emails_callback' ),
			'matrix-donations-settings'
		);

		add_settings_field(
			'notification_admin_emails',
			__( 'Admin Notification Emails', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array( 'field' => 'notification_admin_emails' )
		);

		add_settings_field(
			'notification_admin_subject',
			__( 'Admin Email Subject', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array( 'field' => 'notification_admin_subject' )
		);

		add_settings_field(
			'notification_admin_body',
			__( 'Admin Email Body', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array(
				'field' => 'notification_admin_body',
				'rows'  => 7,
			)
		);

		add_settings_field(
			'notification_user_subject',
			__( 'Donor Email Subject', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array( 'field' => 'notification_user_subject' )
		);

		add_settings_field(
			'notification_user_body',
			__( 'Donor Email Body', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array(
				'field' => 'notification_user_body',
				'rows'  => 7,
			)
		);

		add_settings_field(
			'notification_test_recipient',
			__( 'Test Recipient Email', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_emails',
			array(
				'field' => 'notification_test_recipient',
				'type'  => 'email',
			)
		);

		add_settings_section(
			'matrix_donations_thank_you',
			__( 'Thank You Page', 'matrix-donations' ),
			array( $this, 'section_thank_you_callback' ),
			'matrix-donations-settings'
		);

		add_settings_field(
			'thank_you_heading_line_1',
			__( 'Heading Line 1', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_heading_line_1' )
		);

		add_settings_field(
			'thank_you_heading_line_2',
			__( 'Heading Line 2', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_heading_line_2' )
		);

		add_settings_field(
			'thank_you_heading_line_3',
			__( 'Heading Line 3', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_heading_line_3' )
		);

		add_settings_field(
			'thank_you_note',
			__( 'Thank You Note', 'matrix-donations' ),
			array( $this, 'field_textarea_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array(
				'field' => 'thank_you_note',
				'rows'  => 3,
			)
		);

		add_settings_field(
			'thank_you_primary_label',
			__( 'Primary Button Label', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_primary_label' )
		);

		add_settings_field(
			'thank_you_primary_url',
			__( 'Primary Button URL', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array(
				'field' => 'thank_you_primary_url',
				'type'  => 'url',
			)
		);

		add_settings_field(
			'thank_you_secondary_label',
			__( 'Secondary Button Label', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_secondary_label' )
		);

		add_settings_field(
			'thank_you_secondary_url',
			__( 'Secondary Button URL', 'matrix-donations' ),
			array( $this, 'field_text_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array(
				'field' => 'thank_you_secondary_url',
				'type'  => 'url',
			)
		);

		add_settings_field(
			'thank_you_image',
			__( 'Thank You Image', 'matrix-donations' ),
			array( $this, 'field_media_callback' ),
			'matrix-donations-settings',
			'matrix_donations_thank_you',
			array( 'field' => 'thank_you_image' )
		);
	}

	public function section_api_callback() {
		echo '<p>' . esc_html__( 'Configure Stripe keys and mode. Keep live credentials only in Live mode.', 'matrix-donations' ) . '</p>';
	}

	public function section_tax_callback() {
		echo '<p>' . esc_html__( 'Configure tax application for checkout totals.', 'matrix-donations' ) . '</p>';
	}

	public function section_testing_callback() {
		echo '<p>' . esc_html__( 'Controls for diagnostics, mock checkout, technical alerts, testing helpers, and optional GitHub-triggered Playwright runs from wp-admin.', 'matrix-donations' ) . '</p>';
	}

	public function section_pages_callback() {
		echo '<p>' . esc_html__( 'Select pages for donation flow and Stripe redirect outcomes.', 'matrix-donations' ) . '</p>';
	}

	public function section_content_callback() {
		echo '<p>' . esc_html__( 'Content used by donation templates.', 'matrix-donations' ) . '</p>';
	}

	public function section_design_callback() {
		echo '<p>' . esc_html__( 'Optional style controls for reuse across sites. Leave disabled to keep the current design unchanged.', 'matrix-donations' ) . '</p>';
	}

	public function section_emails_callback() {
		echo '<p>' . esc_html__( 'Emails sent after a successful payment. Available placeholders: {first_name}, {last_name}, {full_name}, {email}, {donation_type}, {amount}, {currency}, {amount_with_currency}, {site_name}, {site_url}, {date}.', 'matrix-donations' ) . '</p>';
	}

	public function section_thank_you_callback() {
		echo '<p>' . esc_html__( 'Customize the success page shown after a completed donation.', 'matrix-donations' ) . '</p>';
	}

	public function field_text_callback( $args ) {
		$opts  = $this->get_options();
		$field = $args['field'];
		$type  = $args['type'] ?? 'text';
		$value = $opts[ $field ] ?? '';
		$step  = isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
		$min   = isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '';
		$max   = isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '';
		$placeholder = isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';
		?>
		<input type="<?php echo esc_attr( $type ); ?>"
			id="matrix_donations_<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			<?php echo $step; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $min; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $max; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			autocomplete="off" />
		<?php
	}

	public function field_textarea_callback( $args ) {
		$opts  = $this->get_options();
		$field = $args['field'];
		$value = $opts[ $field ] ?? '';
		$rows  = isset( $args['rows'] ) ? absint( $args['rows'] ) : 4;
		?>
		<textarea id="matrix_donations_<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>"
			rows="<?php echo esc_attr( $rows ); ?>"
			class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public function field_color_callback( $args ) {
		$opts  = $this->get_options();
		$field = $args['field'];
		$value = $opts[ $field ] ?? '';
		?>
		<input type="color"
			id="matrix_donations_<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>"
			value="<?php echo esc_attr( $value ? $value : '#00628f' ); ?>" />
		<input type="text"
			value="<?php echo esc_attr( $value ); ?>"
			readonly="readonly"
			class="regular-text"
			style="margin-left:8px;max-width:120px;" />
		<?php
	}

	public function field_select_callback( $args ) {
		$opts    = $this->get_options();
		$field   = $args['field'];
		$value   = (string) ( $opts[ $field ] ?? '' );
		$options = $args['options'] ?? array();
		?>
		<select id="matrix_donations_<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>">
			<?php foreach ( $options as $opt_val => $opt_label ) : ?>
				<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, (string) $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function field_page_select_callback( $args ) {
		$opts  = $this->get_options();
		$field = $args['field'];
		$value = isset( $opts[ $field ] ) ? absint( $opts[ $field ] ) : 0;
		$pages = get_pages( array( 'sort_column' => 'menu_order,post_title' ) );
		?>
		<select id="matrix_donations_<?php echo esc_attr( $field ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>"
			class="regular-text">
			<option value="0"><?php esc_html_e( '— Select a page —', 'matrix-donations' ); ?></option>
			<?php foreach ( $pages as $page ) : ?>
				<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $value, $page->ID ); ?>>
					<?php echo esc_html( $page->post_title ); ?> (ID: <?php echo (int) $page->ID; ?>)
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function field_media_callback( $args ) {
		$opts  = $this->get_options();
		$field = $args['field'];
		$value = isset( $opts[ $field ] ) ? absint( $opts[ $field ] ) : 0;
		$url   = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
		?>
		<div class="matrix-donations-media-wrap">
			<input type="hidden"
				id="matrix_donations_<?php echo esc_attr( $field ); ?>"
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $field . ']' ); ?>"
				value="<?php echo esc_attr( $value ); ?>" />
			<div class="matrix-donations-media-preview" style="margin-bottom:8px;">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width:200px;height:auto;display:block;" />
				<?php endif; ?>
			</div>
			<button type="button" class="button matrix-donations-upload" data-field="<?php echo esc_attr( $field ); ?>">
				<?php echo $value ? esc_html__( 'Change image', 'matrix-donations' ) : esc_html__( 'Select image', 'matrix-donations' ); ?>
			</button>
			<?php if ( $value ) : ?>
				<button type="button" class="button matrix-donations-remove" data-field="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'Remove', 'matrix-donations' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	public function sanitize_options( $input ) {
		$sanitized = array();

		$text_fields = array(
			'stripe_mode',
			'stripe_test_secret_key',
			'stripe_live_secret_key',
			'stripe_test_publishable_key',
			'stripe_live_publishable_key',
			'stripe_test_webhook_secret',
			'stripe_live_webhook_secret',
			'salesforce_url',
			'salesforce_org_id',
			'content_heading',
			'content_info',
			'content_see_more_url',
			'content_see_more_title',
			'content_see_more_target',
			'content_form_impact_message',
			'content_form_impact_enabled',
			'design_enable_custom_styles',
			'design_button_bg',
			'design_button_text',
			'design_button_border',
			'design_button_hover_bg',
			'design_button_hover_text',
			'design_button_hover_border',
			'design_button_active_bg',
			'design_button_active_text',
			'design_button_focus_ring',
			'design_thank_you_primary_bg',
			'design_thank_you_primary_text',
			'design_thank_you_secondary_bg',
			'design_thank_you_secondary_text',
			'design_thank_you_secondary_border',
			'notification_admin_emails',
			'notification_admin_subject',
			'notification_user_subject',
			'notification_test_recipient',
			'technical_alert_emails',
			'e2e_github_repository',
			'e2e_github_workflow',
			'e2e_github_ref',
			'e2e_github_token',
			'testing_cards_enabled',
			'debug_enabled',
			'mock_checkout_enabled',
			'checkout_tax_enabled',
			'technical_alerts_enabled',
			'thank_you_heading_line_1',
			'thank_you_heading_line_2',
			'thank_you_heading_line_3',
			'thank_you_primary_label',
			'thank_you_primary_url',
			'thank_you_secondary_label',
			'thank_you_secondary_url',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		$page_fields = array(
			'page_single',
			'page_monthly',
			'page_membership',
			'page_renew_membership',
			'checkout_page_id',
			'success_page_id',
			'cancel_page_id',
			'content_feature_image',
			'content_banner',
			'thank_you_image',
		);
		foreach ( $page_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = absint( $input[ $field ] );
			}
		}

		if ( isset( $input['content_description'] ) ) {
			$sanitized['content_description'] = wp_kses_post( $input['content_description'] );
		}
		if ( isset( $input['content_details'] ) ) {
			$sanitized['content_details'] = wp_kses_post( $input['content_details'] );
		}
		if ( isset( $input['content_form_info_message'] ) ) {
			$sanitized['content_form_info_message'] = wp_kses_post( $input['content_form_info_message'] );
		}
		if ( isset( $input['notification_admin_body'] ) ) {
			$sanitized['notification_admin_body'] = sanitize_textarea_field( $input['notification_admin_body'] );
		}
		if ( isset( $input['notification_user_body'] ) ) {
			$sanitized['notification_user_body'] = sanitize_textarea_field( $input['notification_user_body'] );
		}
		if ( isset( $input['notification_test_recipient'] ) ) {
			$sanitized['notification_test_recipient'] = sanitize_email( $input['notification_test_recipient'] );
		}
		if ( isset( $input['thank_you_note'] ) ) {
			$sanitized['thank_you_note'] = sanitize_textarea_field( $input['thank_you_note'] );
		}
		if ( isset( $input['testing_cards_reference'] ) ) {
			$sanitized['testing_cards_reference'] = sanitize_textarea_field( $input['testing_cards_reference'] );
		}
		if ( isset( $input['design_custom_css'] ) ) {
			$sanitized['design_custom_css'] = wp_strip_all_tags( (string) $input['design_custom_css'] );
		}

		if ( empty( $sanitized['stripe_mode'] ) || ! in_array( $sanitized['stripe_mode'], array( 'test', 'live' ), true ) ) {
			$sanitized['stripe_mode'] = 'test';
		}
		if ( empty( $sanitized['debug_enabled'] ) || ! in_array( $sanitized['debug_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['debug_enabled'] = '0';
		}
		if ( empty( $sanitized['mock_checkout_enabled'] ) || ! in_array( $sanitized['mock_checkout_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['mock_checkout_enabled'] = '0';
		}
		if ( ! isset( $sanitized['checkout_tax_enabled'] ) || ! in_array( (string) $sanitized['checkout_tax_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['checkout_tax_enabled'] = '1';
		}
		if ( ! isset( $sanitized['technical_alerts_enabled'] ) || ! in_array( (string) $sanitized['technical_alerts_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['technical_alerts_enabled'] = '0';
		}
		if ( ! isset( $sanitized['content_form_impact_enabled'] ) || ! in_array( (string) $sanitized['content_form_impact_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['content_form_impact_enabled'] = '1';
		}
		if ( ! isset( $sanitized['testing_cards_enabled'] ) || ! in_array( (string) $sanitized['testing_cards_enabled'], array( '0', '1' ), true ) ) {
			$sanitized['testing_cards_enabled'] = '1';
		}
		if ( ! isset( $sanitized['design_enable_custom_styles'] ) || ! in_array( (string) $sanitized['design_enable_custom_styles'], array( '0', '1' ), true ) ) {
			$sanitized['design_enable_custom_styles'] = '0';
		}
		$tax_percentage = isset( $input['checkout_tax_percentage'] ) ? (float) $input['checkout_tax_percentage'] : 20.0;
		if ( $tax_percentage < 0 ) {
			$tax_percentage = 0;
		}
		if ( $tax_percentage > 100 ) {
			$tax_percentage = 100;
		}
		$sanitized['checkout_tax_percentage'] = $tax_percentage;

		return $sanitized;
	}

	public function render_donations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selected_mode   = isset( $_GET['donation_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['donation_mode'] ) ) : '';
		$selected_status = isset( $_GET['donation_status'] ) ? sanitize_text_field( wp_unslash( $_GET['donation_status'] ) ) : '';
		$search_term     = isset( $_GET['donation_q'] ) ? sanitize_text_field( wp_unslash( $_GET['donation_q'] ) ) : '';
		$current_page    = isset( $_GET['donations_paged'] ) ? max( 1, absint( $_GET['donations_paged'] ) ) : 1;
		$per_page        = 50;

		$stats = Matrix_Donations_Donations_Repository::get_summary_stats();
		$total_filtered = Matrix_Donations_Donations_Repository::get_count(
			array(
				'mode'   => $selected_mode,
				'status' => $selected_status,
				'search' => $search_term,
			)
		);
		$total_pages = max( 1, (int) ceil( $total_filtered / $per_page ) );
		if ( $current_page > $total_pages ) {
			$current_page = $total_pages;
		}
		$donations = Matrix_Donations_Donations_Repository::get_recent(
			array(
				'limit'  => $per_page,
				'page'   => $current_page,
				'mode'   => $selected_mode,
				'status' => $selected_status,
				'search' => $search_term,
			)
		);
		$active_mode = Matrix_Donations_Settings::get_mode();
		$test_cards_enabled = '1' === (string) self::get( 'testing_cards_enabled' );
		$test_cards_reference = (string) self::get( 'testing_cards_reference' );
		$last_diagnostics = self::get_last_quick_diagnostics();
		$last_smoke_tests = self::get_last_smoke_tests();
		$last_full_e2e = self::get_last_full_e2e_dispatch();
		$last_e2e_status = self::get_last_e2e_status();
		$last_webhook_status = class_exists( 'Matrix_Donations_Webhook' ) ? Matrix_Donations_Webhook::get_last_webhook_status() : array();
		$test_key_ok = self::starts_with_any( (string) self::get( 'stripe_test_secret_key' ), array( 'sk_test_', 'rk_test_' ) );
		$live_key_ok = self::starts_with_any( (string) self::get( 'stripe_live_secret_key' ), array( 'sk_live_', 'rk_live_' ) );
		$test_webhook_ok = self::starts_with_any( (string) self::get( 'stripe_test_webhook_secret' ), array( 'whsec_' ) );
		$live_webhook_ok = self::starts_with_any( (string) self::get( 'stripe_live_webhook_secret' ), array( 'whsec_' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Matrix Donations', 'matrix-donations' ); ?></h1>
			<p><?php esc_html_e( 'Recent donation activity captured from Stripe checkout/webhooks.', 'matrix-donations' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0 14px;">
				<input type="hidden" name="action" value="matrix_donations_run_quick_diagnostics" />
				<?php wp_nonce_field( 'matrix_donations_run_quick_diagnostics', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Run Quick Diagnostics', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 8px 10px 0;">
				<input type="hidden" name="action" value="matrix_donations_run_smoke_tests" />
				<?php wp_nonce_field( 'matrix_donations_run_smoke_tests', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Run Smoke Tests', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 8px 10px 0;">
				<input type="hidden" name="action" value="matrix_donations_run_full_e2e" />
				<?php wp_nonce_field( 'matrix_donations_run_full_e2e', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Run Full Playwright E2E', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 8px 10px 0;">
				<input type="hidden" name="action" value="matrix_donations_mark_e2e_status" />
				<input type="hidden" name="status" value="passed" />
				<?php wp_nonce_field( 'matrix_donations_mark_e2e_status', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Mark E2E Passed', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 0 10px 0;">
				<input type="hidden" name="action" value="matrix_donations_mark_e2e_status" />
				<input type="hidden" name="status" value="failed" />
				<?php wp_nonce_field( 'matrix_donations_mark_e2e_status', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Mark E2E Failed', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( ! empty( $last_diagnostics['message'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last Diagnostics:', 'matrix-donations' ); ?></strong>
					<?php echo esc_html( $last_diagnostics['message'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $last_smoke_tests['message'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last Smoke Tests:', 'matrix-donations' ); ?></strong>
					<?php echo esc_html( $last_smoke_tests['message'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $last_full_e2e['message'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last Full E2E Dispatch:', 'matrix-donations' ); ?></strong>
					<?php echo esc_html( $last_full_e2e['message'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $last_e2e_status['message'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last E2E Status:', 'matrix-donations' ); ?></strong>
					<?php echo esc_html( $last_e2e_status['message'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( 'test' === $active_mode && $test_cards_enabled && '' !== trim( $test_cards_reference ) ) : ?>
				<div class="notice notice-info inline">
					<p><strong><?php esc_html_e( 'Test Card Reference', 'matrix-donations' ); ?></strong></p>
					<pre style="white-space:pre-wrap;margin:0;"><?php echo esc_html( $test_cards_reference ); ?></pre>
				</div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:960px;margin-bottom:16px;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Current Site Context', 'matrix-donations' ); ?></th>
						<td>
							<?php
							$site_context = $this->get_site_context_label();
							echo esc_html( $site_context );
							?>
						</td>
					</tr>
					<tr>
						<th style="width:240px;"><?php esc_html_e( 'Active Stripe Mode', 'matrix-donations' ); ?></th>
						<td><?php echo esc_html( strtoupper( $active_mode ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Webhook Endpoint', 'matrix-donations' ); ?></th>
						<td><code><?php echo esc_html( rest_url( 'matrix-donations/v1/webhook' ) ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last Webhook Health', 'matrix-donations' ); ?></th>
						<td>
							<?php
							if ( ! empty( $last_webhook_status['timestamp'] ) ) {
								$parts = array(
									! empty( $last_webhook_status['success'] ) ? 'ok' : 'error',
									(string) ( $last_webhook_status['event'] ?? '' ),
									(string) ( $last_webhook_status['timestamp'] ?? '' ),
									(string) ( $last_webhook_status['message'] ?? '' ),
								);
								echo esc_html( implode( ' | ', array_filter( $parts ) ) );
							} else {
								esc_html_e( 'No webhook activity recorded yet.', 'matrix-donations' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Test Credentials', 'matrix-donations' ); ?></th>
						<td><?php echo esc_html( ( $test_key_ok && $test_webhook_ok ) ? __( 'Configured', 'matrix-donations' ) : __( 'Incomplete', 'matrix-donations' ) ); ?><?php echo esc_html( sprintf( ' (key: %s, webhook: %s)', $test_key_ok ? 'ok' : 'missing', $test_webhook_ok ? 'ok' : 'missing' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Live Credentials', 'matrix-donations' ); ?></th>
						<td><?php echo esc_html( ( $live_key_ok && $live_webhook_ok ) ? __( 'Configured', 'matrix-donations' ) : __( 'Incomplete', 'matrix-donations' ) ); ?><?php echo esc_html( sprintf( ' (key: %s, webhook: %s)', $live_key_ok ? 'ok' : 'missing', $live_webhook_ok ? 'ok' : 'missing' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Donation Totals', 'matrix-donations' ); ?></th>
						<td>
							<?php
							$totals = array( 'total: ' . (int) ( $stats['total'] ?? 0 ) );
							foreach ( (array) ( $stats['by_mode'] ?? array() ) as $mode => $count ) {
								$totals[] = $mode . ': ' . (int) $count;
							}
							foreach ( (array) ( $stats['by_status'] ?? array() ) as $status => $count ) {
								$totals[] = $status . ': ' . (int) $count;
							}
							echo esc_html( implode( ' | ', $totals ) );
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:12px;">
				<input type="hidden" name="page" value="matrix-donations" />
				<input type="hidden" name="donations_paged" value="1" />
				<select name="donation_mode">
					<option value=""><?php esc_html_e( 'All modes', 'matrix-donations' ); ?></option>
					<option value="test" <?php selected( $selected_mode, 'test' ); ?>><?php esc_html_e( 'Test', 'matrix-donations' ); ?></option>
					<option value="live" <?php selected( $selected_mode, 'live' ); ?>><?php esc_html_e( 'Live', 'matrix-donations' ); ?></option>
					<option value="mock" <?php selected( $selected_mode, 'mock' ); ?>><?php esc_html_e( 'Mock', 'matrix-donations' ); ?></option>
				</select>
				<select name="donation_status">
					<option value=""><?php esc_html_e( 'All statuses', 'matrix-donations' ); ?></option>
					<option value="pending" <?php selected( $selected_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'matrix-donations' ); ?></option>
					<option value="paid" <?php selected( $selected_status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'matrix-donations' ); ?></option>
					<option value="failed" <?php selected( $selected_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'matrix-donations' ); ?></option>
				</select>
				<input type="text" name="donation_q" placeholder="<?php esc_attr_e( 'Search email, name or Stripe ID', 'matrix-donations' ); ?>" value="<?php echo esc_attr( $search_term ); ?>" class="regular-text" />
				<?php submit_button( __( 'Filter', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-donations' ) ); ?>"><?php esc_html_e( 'Reset', 'matrix-donations' ); ?></a>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Donor', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Type', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Status', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Mode', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Stripe References', 'matrix-donations' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $donations ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No donations matched your filters yet. Check Stripe mode, webhook setup, and recent logs.', 'matrix-donations' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $donations as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( trim( $row['donor_first_name'] . ' ' . $row['donor_last_name'] ) ); ?><br /><small><?php echo esc_html( $row['donor_email'] ); ?></small></td>
								<td><?php echo esc_html( $row['donation_type'] ); ?></td>
								<td><?php echo esc_html( strtoupper( $row['currency'] ) ); ?> <?php echo esc_html( number_format_i18n( ( (int) $row['amount_cents'] ) / 100, 2 ) ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td><?php echo esc_html( $row['donation_mode'] ); ?></td>
								<td>
									<small>
										<?php if ( ! empty( $row['stripe_session_id'] ) ) : ?>
											<?php echo esc_html( 'session: ' . $this->shorten_reference( $row['stripe_session_id'] ) ); ?>
											<button type="button" class="button-link matrix-copy-ref" data-value="<?php echo esc_attr( $row['stripe_session_id'] ); ?>"><?php esc_html_e( 'copy', 'matrix-donations' ); ?></button><br />
										<?php endif; ?>
										<?php if ( ! empty( $row['stripe_payment_intent_id'] ) ) : ?>
											<?php echo esc_html( 'pi: ' . $this->shorten_reference( $row['stripe_payment_intent_id'] ) ); ?>
											<button type="button" class="button-link matrix-copy-ref" data-value="<?php echo esc_attr( $row['stripe_payment_intent_id'] ); ?>"><?php esc_html_e( 'copy', 'matrix-donations' ); ?></button><br />
										<?php endif; ?>
										<?php if ( ! empty( $row['stripe_subscription_id'] ) ) : ?>
											<?php echo esc_html( 'sub: ' . $this->shorten_reference( $row['stripe_subscription_id'] ) ); ?>
											<button type="button" class="button-link matrix-copy-ref" data-value="<?php echo esc_attr( $row['stripe_subscription_id'] ); ?>"><?php esc_html_e( 'copy', 'matrix-donations' ); ?></button><br />
										<?php endif; ?>
										<?php if ( ! empty( $row['stripe_event_id'] ) ) : ?>
											<?php echo esc_html( 'event: ' . $this->shorten_reference( $row['stripe_event_id'] ) ); ?>
											<button type="button" class="button-link matrix-copy-ref" data-value="<?php echo esc_attr( $row['stripe_event_id'] ); ?>"><?php esc_html_e( 'copy', 'matrix-donations' ); ?></button>
										<?php endif; ?>
									</small>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			$pagination_links = paginate_links(
				array(
					'base'      => add_query_arg(
						array(
							'page'            => 'matrix-donations',
							'donation_mode'   => $selected_mode,
							'donation_status' => $selected_status,
							'donation_q'      => $search_term,
							'donations_paged' => '%#%',
						),
						admin_url( 'admin.php' )
					),
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'array',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			if ( ! empty( $pagination_links ) ) :
				?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( __( '%d items', 'matrix-donations' ), (int) $total_filtered ) ); ?></span>
						<span class="pagination-links">
							<?php foreach ( $pagination_links as $link_html ) : ?>
								<?php echo wp_kses_post( $link_html ); ?>
							<?php endforeach; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['settings_tab'] ) ? sanitize_key( wp_unslash( $_GET['settings_tab'] ) ) : 'stripe';
		$allowed_tabs = array( 'stripe', 'pages', 'content', 'design', 'tax', 'testing', 'emails', 'thankyou' );
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'stripe';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Matrix Donations Settings', 'matrix-donations' ); ?></h1>
			<style>
				.matrix-donations-settings-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 16px}
				.matrix-donations-settings-tabs .matrix-settings-tab.is-active{background:#2271b1;color:#fff;border-color:#2271b1}
				.matrix-settings-panel{margin-bottom:12px}
			</style>
			<nav class="matrix-donations-settings-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'matrix-donations' ); ?>">
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'stripe' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'stripe' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Stripe', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'pages' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'pages' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Pages', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'content' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'content' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Content', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'design' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'design' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Design', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'tax' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'tax' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Tax', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'testing' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'testing' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Testing', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'emails' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'emails' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Emails', 'matrix-donations' ); ?></a>
				<a class="button button-secondary matrix-settings-tab <?php echo ( 'thankyou' === $tab ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'matrix-donations-settings', 'settings_tab' => 'thankyou' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Thank You', 'matrix-donations' ); ?></a>
			</nav>
			<form action="options.php" method="post" class="matrix-donations-settings-form">
				<?php
				settings_fields( self::OPTION_GROUP );
				$this->render_settings_tab_sections( $tab );
				submit_button( __( 'Save Settings', 'matrix-donations' ) );
				?>
			</form>
			<?php if ( 'emails' === $tab ) : ?>
				<hr />
				<h2><?php esc_html_e( 'Email Template Test', 'matrix-donations' ); ?></h2>
				<p><?php esc_html_e( 'Sends both admin and donor template emails using sample donation data. Save settings first if you changed templates.', 'matrix-donations' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="matrix_donations_send_test_emails" />
					<?php wp_nonce_field( 'matrix_donations_send_test_emails', 'matrix_donations_nonce' ); ?>
					<?php submit_button( __( 'Send Test Emails', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( 'stripe' === $tab ) : ?>
				<hr />
				<h2><?php esc_html_e( 'Stripe Setup Status', 'matrix-donations' ); ?></h2>
				<p><?php esc_html_e( 'Use this panel to verify required Stripe settings and run a live API validation for test or live mode.', 'matrix-donations' ); ?></p>
				<table class="widefat striped" style="max-width:980px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Check', 'matrix-donations' ); ?></th>
							<th><?php esc_html_e( 'Status', 'matrix-donations' ); ?></th>
							<th><?php esc_html_e( 'Details', 'matrix-donations' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->get_stripe_status_rows() as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['label'] ); ?></td>
								<td><?php echo esc_html( $row['ok'] ? __( 'OK', 'matrix-donations' ) : __( 'Missing / Invalid', 'matrix-donations' ) ); ?></td>
								<td><?php echo esc_html( $row['details'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top:10px;">
					<code><?php echo esc_html( rest_url( 'matrix-donations/v1/webhook' ) ); ?></code>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:10px;">
					<input type="hidden" name="action" value="matrix_donations_validate_stripe" />
					<input type="hidden" name="validation_mode" value="test" />
					<?php wp_nonce_field( 'matrix_donations_validate_stripe', 'matrix_donations_nonce' ); ?>
					<?php submit_button( __( 'Validate Test Stripe Keys', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="matrix_donations_validate_stripe" />
					<input type="hidden" name="validation_mode" value="live" />
					<?php wp_nonce_field( 'matrix_donations_validate_stripe', 'matrix_donations_nonce' ); ?>
					<?php submit_button( __( 'Validate Live Stripe Keys', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render only settings sections for the active settings tab.
	 *
	 * @param string $tab Active tab key.
	 * @return void
	 */
	private function render_settings_tab_sections( $tab ) {
		global $wp_settings_sections, $wp_settings_fields;
		$page = 'matrix-donations-settings';
		if ( empty( $wp_settings_sections[ $page ] ) ) {
			return;
		}

		$tab_to_sections = array(
			'stripe'   => array( 'matrix_donations_api' ),
			'pages'    => array( 'matrix_donations_pages' ),
			'content'  => array( 'matrix_donations_content' ),
			'design'   => array( 'matrix_donations_design' ),
			'tax'      => array( 'matrix_donations_tax' ),
			'testing'  => array( 'matrix_donations_testing' ),
			'emails'   => array( 'matrix_donations_emails' ),
			'thankyou' => array( 'matrix_donations_thank_you' ),
		);
		$allowed_section_ids = $tab_to_sections[ $tab ] ?? array();
		foreach ( (array) $wp_settings_sections[ $page ] as $section_id => $section ) {
			if ( ! in_array( $section_id, $allowed_section_ids, true ) ) {
				continue;
			}
			if ( '' !== $section['title'] ) {
				echo "<h2>{$section['title']}</h2>";
			}
			if ( ! empty( $section['callback'] ) ) {
				call_user_func( $section['callback'], $section );
			}
			if ( ! empty( $wp_settings_fields[ $page ][ $section_id ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( $page, $section_id );
				echo '</table>';
			}
		}
	}

	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$logs = Matrix_Donations_Logger::get_logs( 500 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Matrix Donations Logs', 'matrix-donations' ); ?></h1>
			<p><?php esc_html_e( 'Logs are only written when Debug Logging is enabled in Settings.', 'matrix-donations' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
				<input type="hidden" name="action" value="matrix_donations_clear_logs" />
				<?php wp_nonce_field( 'matrix_donations_clear_logs', 'matrix_donations_nonce' ); ?>
				<?php submit_button( __( 'Clear Logs', 'matrix-donations' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Level', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Message', 'matrix-donations' ); ?></th>
						<th><?php esc_html_e( 'Context', 'matrix-donations' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No logs yet.', 'matrix-donations' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['timestamp'] ?? '' ); ?></td>
								<td><?php echo esc_html( strtoupper( $entry['level'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( wp_json_encode( $entry['context'] ?? array() ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render setup guide tab for colleague handover and multisite rollouts.
	 *
	 * @return void
	 */
	public function render_setup_guide_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$site_context = $this->get_site_context_label();
		$webhook_url  = rest_url( 'matrix-donations/v1/webhook' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Matrix Donations Setup Guide', 'matrix-donations' ); ?></h1>
			<p><?php esc_html_e( 'Use this checklist when onboarding a new site, including multisite sub-sites.', 'matrix-donations' ); ?></p>

			<table class="widefat striped" style="max-width:980px;margin-bottom:16px;">
				<tbody>
					<tr>
						<th style="width:240px;"><?php esc_html_e( 'Current Site Context', 'matrix-donations' ); ?></th>
						<td><?php echo esc_html( $site_context ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Webhook Endpoint for This Site', 'matrix-donations' ); ?></th>
						<td><code><?php echo esc_html( $webhook_url ); ?></code></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Per New Site Checklist', 'matrix-donations' ); ?></h2>
			<ol style="margin-left:18px;">
				<li><?php esc_html_e( 'Configure Stripe keys for test and live modes.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Create Stripe webhook destination for this site endpoint and subscribe to checkout + subscription lifecycle events.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Save webhook signing secret (whsec) per mode in plugin settings.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Map checkout/success/cancel pages in plugin settings.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Run one test donation and one test email from backend to verify flow.', 'matrix-donations' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'Recommended events:', 'matrix-donations' ); ?></strong> <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>invoice.payment_succeeded</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code></p>

			<h2><?php esc_html_e( 'Operational Notes', 'matrix-donations' ); ?></h2>
			<ul style="margin-left:18px;list-style:disc;">
				<li><?php esc_html_e( 'Follow your organization policy for Stripe account structure.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Do not reuse webhook signing secrets between different sites or environments.', 'matrix-donations' ); ?></li>
				<li><?php esc_html_e( 'Each site URL requires its own webhook destination and secret.', 'matrix-donations' ); ?></li>
			</ul>
		</div>
		<?php
	}

	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_clear_logs', 'matrix_donations_nonce' );
		Matrix_Donations_Logger::clear_logs();
		wp_safe_redirect( admin_url( 'admin.php?page=matrix-donations-logs' ) );
		exit;
	}

	/**
	 * Trigger test email send for admin + donor templates.
	 *
	 * @return void
	 */
	public function handle_send_test_emails() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_send_test_emails', 'matrix_donations_nonce' );

		$test_recipient = sanitize_email( (string) self::get( 'notification_test_recipient' ) );
		$sent           = Matrix_Donations_Notifications::send_test_emails( $test_recipient );
		$result         = $sent ? 'sent' : 'failed';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'matrix-donations-settings',
					'matrix_email_test' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Run quick diagnostics from Donations screen.
	 *
	 * @return void
	 */
	public function handle_run_quick_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_run_quick_diagnostics', 'matrix_donations_nonce' );

		$result = $this->run_quick_diagnostics();
		self::set_last_quick_diagnostics( $result );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => 'matrix-donations',
					'matrix_quick_diagnostics'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Run lightweight smoke tests against configured donation pages.
	 *
	 * @return void
	 */
	public function handle_run_smoke_tests() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_run_smoke_tests', 'matrix_donations_nonce' );

		$result = $this->run_smoke_tests();
		self::set_last_smoke_tests( $result );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'matrix-donations',
					'matrix_smoke_tests' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Trigger GitHub Actions workflow_dispatch for full Playwright suite.
	 *
	 * @return void
	 */
	public function handle_run_full_e2e() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_run_full_e2e', 'matrix_donations_nonce' );

		$result = $this->run_full_e2e_dispatch();
		self::set_last_full_e2e_dispatch( $result );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'matrix-donations',
					'matrix_full_e2e' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Manually mark latest E2E status in admin.
	 *
	 * @return void
	 */
	public function handle_mark_e2e_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_mark_e2e_status', 'matrix_donations_nonce' );

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'failed';
		if ( ! in_array( $status, array( 'passed', 'failed' ), true ) ) {
			$status = 'failed';
		}
		self::set_last_e2e_status(
			array(
				'success'   => ( 'passed' === $status ),
				'message'   => sprintf( __( 'Manually marked as %1$s at %2$s.', 'matrix-donations' ), strtoupper( $status ), current_time( 'mysql' ) ),
				'timestamp' => current_time( 'mysql' ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'matrix-donations',
					'matrix_e2e_status' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_mock_mode_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_mock_checkout_enabled() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'matrix-donations' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'Matrix Donations: Mock Checkout is enabled.', 'matrix-donations' ); ?></strong> <?php esc_html_e( 'Real Stripe payments are bypassed until this is disabled.', 'matrix-donations' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show status notice after triggering Send Test Emails.
	 *
	 * @return void
	 */
	public function render_test_email_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$result = isset( $_GET['matrix_email_test'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_email_test'] ) ) : '';
		if ( 'matrix-donations-settings' !== $page || '' === $result ) {
			return;
		}

		$class   = 'notice notice-success';
		$message = __( 'Test emails sent. Please check inbox and spam folders.', 'matrix-donations' );
		if ( 'failed' === $result ) {
			$class   = 'notice notice-error';
			$message = __( 'Test emails could not be sent. Check your mail configuration and Matrix Donations logs.', 'matrix-donations' );
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show notice after quick diagnostics run.
	 *
	 * @return void
	 */
	public function render_quick_diagnostics_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$flag = isset( $_GET['matrix_quick_diagnostics'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_quick_diagnostics'] ) ) : '';
		if ( 'matrix-donations' !== $page || '1' !== $flag ) {
			return;
		}

		$last = self::get_last_quick_diagnostics();
		if ( empty( $last['message'] ) ) {
			return;
		}

		$class = ! empty( $last['success'] ) ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $last['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show notice after smoke tests run.
	 *
	 * @return void
	 */
	public function render_smoke_tests_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$flag = isset( $_GET['matrix_smoke_tests'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_smoke_tests'] ) ) : '';
		if ( 'matrix-donations' !== $page || '1' !== $flag ) {
			return;
		}

		$last = self::get_last_smoke_tests();
		if ( empty( $last['message'] ) ) {
			return;
		}

		$class = ! empty( $last['success'] ) ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $last['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show notice after full E2E dispatch run.
	 *
	 * @return void
	 */
	public function render_full_e2e_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$flag = isset( $_GET['matrix_full_e2e'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_full_e2e'] ) ) : '';
		if ( 'matrix-donations' !== $page || '1' !== $flag ) {
			return;
		}

		$last = self::get_last_full_e2e_dispatch();
		if ( empty( $last['message'] ) ) {
			return;
		}

		$class = ! empty( $last['success'] ) ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $last['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show notice after manually marking E2E status.
	 *
	 * @return void
	 */
	public function render_e2e_status_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$flag = isset( $_GET['matrix_e2e_status'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_e2e_status'] ) ) : '';
		if ( 'matrix-donations' !== $page || '1' !== $flag ) {
			return;
		}

		$last = self::get_last_e2e_status();
		if ( empty( $last['message'] ) ) {
			return;
		}
		$class = ! empty( $last['success'] ) ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $last['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Validate Stripe credentials by creating a disposable checkout session.
	 *
	 * @return void
	 */
	public function handle_validate_stripe() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'matrix-donations' ) );
		}
		check_admin_referer( 'matrix_donations_validate_stripe', 'matrix_donations_nonce' );

		$mode = isset( $_POST['validation_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['validation_mode'] ) ) : 'test';
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) {
			$mode = 'test';
		}

		$result = $this->run_stripe_validation( $mode );
		set_transient( self::get_validation_transient_key(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => 'matrix-donations-settings',
					'matrix_stripe_validation'=> '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Show a notice for the latest manual Stripe validation run.
	 *
	 * @return void
	 */
	public function render_stripe_validation_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$flag = isset( $_GET['matrix_stripe_validation'] ) ? sanitize_text_field( wp_unslash( $_GET['matrix_stripe_validation'] ) ) : '';
		if ( 'matrix-donations-settings' !== $page || '1' !== $flag ) {
			return;
		}

		$result = get_transient( self::get_validation_transient_key() );
		if ( ! is_array( $result ) || empty( $result['message'] ) ) {
			return;
		}

		$class = ( ! empty( $result['success'] ) ) ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $result['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Build Stripe setup checklist rows for settings UI.
	 *
	 * @return array
	 */
	private function get_stripe_status_rows() {
		$rows = array();
		$rows[] = array(
			'label'   => __( 'Stripe SDK available', 'matrix-donations' ),
			'ok'      => Matrix_Donations_Stripe_Service::load_stripe(),
			'details' => __( 'Composer Stripe library must be available in plugin or theme vendor folder.', 'matrix-donations' ),
		);
		$rows[] = $this->build_mode_status_row( 'test', 'publishable' );
		$rows[] = $this->build_mode_status_row( 'test', 'secret' );
		$rows[] = $this->build_mode_status_row( 'test', 'webhook' );
		$rows[] = $this->build_mode_status_row( 'live', 'publishable' );
		$rows[] = $this->build_mode_status_row( 'live', 'secret' );
		$rows[] = $this->build_mode_status_row( 'live', 'webhook' );
		return $rows;
	}

	/**
	 * Build one checklist row for mode/credential type.
	 *
	 * @param string $mode test|live.
	 * @param string $type publishable|secret|webhook.
	 * @return array
	 */
	private function build_mode_status_row( $mode, $type ) {
		$key         = '';
		$label       = '';
		$valid_start = array();
		if ( 'publishable' === $type ) {
			$key         = 'stripe_' . $mode . '_publishable_key';
			$label       = sprintf( __( '%s publishable key', 'matrix-donations' ), strtoupper( $mode ) );
			$valid_start = array( 'pk_' . $mode . '_' );
		} elseif ( 'secret' === $type ) {
			$key         = 'stripe_' . $mode . '_secret_key';
			$label       = sprintf( __( '%s secret key', 'matrix-donations' ), strtoupper( $mode ) );
			$valid_start = array( 'sk_' . $mode . '_', 'rk_' . $mode . '_' );
		} else {
			$key         = 'stripe_' . $mode . '_webhook_secret';
			$label       = sprintf( __( '%s webhook secret', 'matrix-donations' ), strtoupper( $mode ) );
			$valid_start = array( 'whsec_' );
		}

		$value = (string) self::get( $key );
		$ok    = self::starts_with_any( $value, $valid_start );
		$hint  = sprintf( __( 'Expected prefix: %s', 'matrix-donations' ), implode( ', ', $valid_start ) );
		if ( '' === trim( $value ) ) {
			$hint = __( 'Not set in plugin settings.', 'matrix-donations' );
		}

		return array(
			'label'   => $label,
			'ok'      => $ok,
			'details' => $hint,
		);
	}

	/**
	 * Execute live API validation for the selected mode.
	 *
	 * @param string $mode test|live.
	 * @return array
	 */
	private function run_stripe_validation( $mode ) {
		if ( ! Matrix_Donations_Stripe_Service::load_stripe() ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe SDK unavailable. Run composer install for matrix-donations.', 'matrix-donations' ),
			);
		}

		$secret_key      = (string) self::get( 'stripe_' . $mode . '_secret_key' );
		$publishable_key = (string) self::get( 'stripe_' . $mode . '_publishable_key' );
		if ( '' === trim( $secret_key ) || '' === trim( $publishable_key ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Validation failed: missing %s publishable or secret key.', 'matrix-donations' ), strtoupper( $mode ) ),
			);
		}

		try {
			\Stripe\Stripe::setApiKey( $secret_key );
			$session = \Stripe\Checkout\Session::create(
				array(
					'mode'          => 'payment',
					'success_url'   => add_query_arg( 'stripe_validate', 'ok', home_url( '/' ) ),
					'cancel_url'    => add_query_arg( 'stripe_validate', 'cancel', home_url( '/' ) ),
					'line_items'    => array(
						array(
							'quantity'   => 1,
							'price_data' => array(
								'currency'     => 'eur',
								'unit_amount'  => 50,
								'product_data' => array(
									'name' => 'Matrix Stripe Validation',
								),
							),
						),
					),
					'metadata'      => array(
						'validation_only' => '1',
						'mode'            => $mode,
						'site_url'        => home_url(),
					),
				)
			);
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: mode, 2: Stripe session ID */
					__( '%1$s validation succeeded. Stripe session created: %2$s', 'matrix-donations' ),
					strtoupper( $mode ),
					sanitize_text_field( (string) ( $session->id ?? '' ) )
				),
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: mode, 2: error message */
					__( '%1$s validation failed: %2$s', 'matrix-donations' ),
					strtoupper( $mode ),
					sanitize_text_field( $e->getMessage() )
				),
			);
		}
	}

	/**
	 * Check if string starts with one of the allowed prefixes.
	 *
	 * @param string $value    Input string.
	 * @param array  $prefixes Allowed prefixes.
	 * @return bool
	 */
	private static function starts_with_any( $value, $prefixes ) {
		$value = (string) $value;
		foreach ( (array) $prefixes as $prefix ) {
			$prefix = (string) $prefix;
			if ( '' !== $prefix && 0 === strpos( $value, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build user-scoped transient key for settings notices.
	 *
	 * @return string
	 */
	private static function get_validation_transient_key() {
		return 'matrix_donations_stripe_validation_' . get_current_user_id();
	}

	/**
	 * Run quick non-invasive diagnostics for donation operations.
	 *
	 * @return array
	 */
	private function run_quick_diagnostics() {
		$issues = array();
		$mode   = self::get_mode();

		if ( ! Matrix_Donations_Stripe_Service::load_stripe() ) {
			$issues[] = __( 'Stripe SDK is missing.', 'matrix-donations' );
		}
		if ( ! self::starts_with_any( (string) self::get( 'stripe_' . $mode . '_secret_key' ), array( 'sk_' . $mode . '_', 'rk_' . $mode . '_' ) ) ) {
			$issues[] = sprintf( __( '%s secret key is missing/invalid.', 'matrix-donations' ), strtoupper( $mode ) );
		}
		if ( ! self::starts_with_any( (string) self::get( 'stripe_' . $mode . '_publishable_key' ), array( 'pk_' . $mode . '_' ) ) ) {
			$issues[] = sprintf( __( '%s publishable key is missing/invalid.', 'matrix-donations' ), strtoupper( $mode ) );
		}
		if ( ! self::starts_with_any( (string) self::get( 'stripe_' . $mode . '_webhook_secret' ), array( 'whsec_' ) ) ) {
			$issues[] = sprintf( __( '%s webhook secret is missing/invalid.', 'matrix-donations' ), strtoupper( $mode ) );
		}
		if ( ! self::get( 'checkout_page_id' ) ) {
			$issues[] = __( 'Checkout page is not configured.', 'matrix-donations' );
		}
		if ( ! self::get( 'success_page_id' ) ) {
			$issues[] = __( 'Success page is not configured.', 'matrix-donations' );
		}

		$message = __( 'Quick diagnostics passed. Core Stripe and page settings look good.', 'matrix-donations' );
		$success = true;
		if ( ! empty( $issues ) ) {
			$success = false;
			$message = __( 'Quick diagnostics found issues:', 'matrix-donations' ) . ' ' . implode( ' ', $issues );
		}

		return array(
			'success'   => $success,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Run simple page-level smoke tests for single/monthly donation forms.
	 *
	 * @return array
	 */
	private function run_smoke_tests() {
		$issues      = array();
		$total_tests = 0;
		$passed      = 0;
		$page_map    = array(
			'single'  => absint( self::get( 'page_single' ) ),
			'monthly' => absint( self::get( 'page_monthly' ) ),
		);

		foreach ( $page_map as $label => $page_id ) {
			$total_tests++;
			if ( ! $page_id ) {
				$issues[] = sprintf( __( '%s page is not configured.', 'matrix-donations' ), ucfirst( $label ) );
				continue;
			}

			$url = get_permalink( $page_id );
			if ( ! $url ) {
				$issues[] = sprintf( __( '%s page URL could not be resolved.', 'matrix-donations' ), ucfirst( $label ) );
				continue;
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 15,
				)
			);
			if ( is_wp_error( $response ) ) {
				$issues[] = sprintf(
					/* translators: 1: page type, 2: error message */
					__( '%1$s page request failed: %2$s', 'matrix-donations' ),
					ucfirst( $label ),
					$response->get_error_message()
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			if ( 200 !== $code ) {
				$issues[] = sprintf(
					/* translators: 1: page type, 2: HTTP status code */
					__( '%1$s page returned HTTP %2$d.', 'matrix-donations' ),
					ucfirst( $label ),
					$code
				);
				continue;
			}

			if ( false === strpos( $body, 'id="salesforceForm"' ) || false === strpos( $body, 'id="checkout-status"' ) ) {
				$issues[] = sprintf( __( '%s page loaded but donation form markers were not found.', 'matrix-donations' ), ucfirst( $label ) );
				continue;
			}

			$passed++;
		}

		$success = empty( $issues );
		$message = sprintf(
			/* translators: 1: passed count, 2: total count */
			__( 'Smoke tests finished: %1$d/%2$d passed.', 'matrix-donations' ),
			$passed,
			$total_tests
		);
		if ( ! $success ) {
			$message .= ' ' . implode( ' ', $issues );
		}

		return array(
			'success'   => $success,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Dispatch full Playwright run through GitHub Actions workflow_dispatch.
	 *
	 * @return array
	 */
	private function run_full_e2e_dispatch() {
		$repo     = trim( (string) self::get( 'e2e_github_repository' ) );
		$workflow = trim( (string) self::get( 'e2e_github_workflow' ) );
		$ref      = trim( (string) self::get( 'e2e_github_ref' ) );
		$token    = trim( (string) self::get( 'e2e_github_token' ) );

		if ( '' === $repo || false === strpos( $repo, '/' ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Full E2E dispatch failed: set E2E GitHub Repository as owner/repo in Testing settings.', 'matrix-donations' ),
				'timestamp' => current_time( 'mysql' ),
			);
		}
		if ( '' === $workflow ) {
			return array(
				'success'   => false,
				'message'   => __( 'Full E2E dispatch failed: set E2E Workflow File in Testing settings.', 'matrix-donations' ),
				'timestamp' => current_time( 'mysql' ),
			);
		}
		if ( '' === $ref ) {
			$ref = 'main';
		}
		if ( '' === $token ) {
			return array(
				'success'   => false,
				'message'   => __( 'Full E2E dispatch failed: set E2E GitHub Token in Testing settings.', 'matrix-donations' ),
				'timestamp' => current_time( 'mysql' ),
			);
		}

		$repo_parts = explode( '/', $repo );
		if ( 2 !== count( $repo_parts ) || '' === trim( (string) $repo_parts[0] ) || '' === trim( (string) $repo_parts[1] ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Full E2E dispatch failed: repository must be formatted as owner/repo.', 'matrix-donations' ),
				'timestamp' => current_time( 'mysql' ),
			);
		}
		$owner    = rawurlencode( trim( (string) $repo_parts[0] ) );
		$repo_name = rawurlencode( trim( (string) $repo_parts[1] ) );
		$endpoint = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/actions/workflows/%3$s/dispatches',
			$owner,
			$repo_name,
			rawurlencode( $workflow )
		);
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'Authorization'        => 'Bearer ' . $token,
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'Matrix-Donations-Plugin',
				),
				'body'    => wp_json_encode(
					array(
						'ref' => $ref,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'Full E2E dispatch failed: %s', 'matrix-donations' ), $response->get_error_message() ),
				'timestamp' => current_time( 'mysql' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $code ) {
			$body = (string) wp_remote_retrieve_body( $response );
			$body = wp_strip_all_tags( $body );
			if ( strlen( $body ) > 250 ) {
				$body = substr( $body, 0, 250 ) . '...';
			}
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'Full E2E dispatch failed (HTTP %1$d). %2$s', 'matrix-donations' ), $code, $body ),
				'timestamp' => current_time( 'mysql' ),
			);
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Full Playwright E2E dispatched to GitHub Actions (%1$s @ %2$s).', 'matrix-donations' ), $repo, $ref ),
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Persist last quick diagnostics result for admin visibility.
	 *
	 * @param array $result Result payload.
	 * @return void
	 */
	private static function set_last_quick_diagnostics( $result ) {
		update_option( 'matrix_donations_last_quick_diagnostics', $result, false );
	}

	/**
	 * Read last quick diagnostics result.
	 *
	 * @return array
	 */
	private static function get_last_quick_diagnostics() {
		$value = get_option( 'matrix_donations_last_quick_diagnostics', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persist last smoke tests result.
	 *
	 * @param array $result Result payload.
	 * @return void
	 */
	private static function set_last_smoke_tests( $result ) {
		update_option( 'matrix_donations_last_smoke_tests', $result, false );
	}

	/**
	 * Read last smoke tests result.
	 *
	 * @return array
	 */
	private static function get_last_smoke_tests() {
		$value = get_option( 'matrix_donations_last_smoke_tests', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persist last full E2E GitHub dispatch result.
	 *
	 * @param array $result Result payload.
	 * @return void
	 */
	private static function set_last_full_e2e_dispatch( $result ) {
		update_option( 'matrix_donations_last_full_e2e_dispatch', $result, false );
	}

	/**
	 * Read last full E2E GitHub dispatch result.
	 *
	 * @return array
	 */
	private static function get_last_full_e2e_dispatch() {
		$value = get_option( 'matrix_donations_last_full_e2e_dispatch', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persist latest E2E status marker.
	 *
	 * @param array $result Status payload.
	 * @return void
	 */
	private static function set_last_e2e_status( $result ) {
		update_option( 'matrix_donations_last_e2e_status', $result, false );
	}

	/**
	 * Read latest E2E status marker.
	 *
	 * @return array
	 */
	private static function get_last_e2e_status() {
		$value = get_option( 'matrix_donations_last_e2e_status', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Build readable current site context for multisite-safe configuration.
	 *
	 * @return string
	 */
	private function get_site_context_label() {
		$parts = array(
			get_bloginfo( 'name' ),
			home_url(),
		);
		if ( is_multisite() ) {
			$parts[] = 'blog_id=' . (int) get_current_blog_id();
		}
		return implode( ' | ', array_filter( $parts ) );
	}

	/**
	 * Get design tokens configured by admin.
	 *
	 * @return array
	 */
	public static function get_design_tokens() {
		$opts = self::get_options();
		return array(
			'enabled'                     => '1' === (string) ( $opts['design_enable_custom_styles'] ?? '0' ),
			'button_bg'                   => sanitize_hex_color( $opts['design_button_bg'] ?? '' ),
			'button_text'                 => sanitize_hex_color( $opts['design_button_text'] ?? '' ),
			'button_border'               => sanitize_hex_color( $opts['design_button_border'] ?? '' ),
			'button_hover_bg'             => sanitize_hex_color( $opts['design_button_hover_bg'] ?? '' ),
			'button_hover_text'           => sanitize_hex_color( $opts['design_button_hover_text'] ?? '' ),
			'button_hover_border'         => sanitize_hex_color( $opts['design_button_hover_border'] ?? '' ),
			'button_active_bg'            => sanitize_hex_color( $opts['design_button_active_bg'] ?? '' ),
			'button_active_text'          => sanitize_hex_color( $opts['design_button_active_text'] ?? '' ),
			'button_focus_ring'           => sanitize_hex_color( $opts['design_button_focus_ring'] ?? '' ),
			'thank_you_primary_bg'        => sanitize_hex_color( $opts['design_thank_you_primary_bg'] ?? '' ),
			'thank_you_primary_text'      => sanitize_hex_color( $opts['design_thank_you_primary_text'] ?? '' ),
			'thank_you_secondary_bg'      => sanitize_hex_color( $opts['design_thank_you_secondary_bg'] ?? '' ),
			'thank_you_secondary_text'    => sanitize_hex_color( $opts['design_thank_you_secondary_text'] ?? '' ),
			'thank_you_secondary_border'  => sanitize_hex_color( $opts['design_thank_you_secondary_border'] ?? '' ),
			'custom_css'                  => (string) ( $opts['design_custom_css'] ?? '' ),
		);
	}

	/**
	 * Generate optional inline CSS for design override tokens.
	 *
	 * @return string
	 */
	public static function get_design_inline_css() {
		$design = self::get_design_tokens();
		if ( empty( $design['enabled'] ) ) {
			return '';
		}

		$vars = array();
		$map  = array(
			'--matrix-d-btn-bg'            => 'button_bg',
			'--matrix-d-btn-text'          => 'button_text',
			'--matrix-d-btn-border'        => 'button_border',
			'--matrix-d-btn-hover-bg'      => 'button_hover_bg',
			'--matrix-d-btn-hover-text'    => 'button_hover_text',
			'--matrix-d-btn-hover-border'  => 'button_hover_border',
			'--matrix-d-btn-active-bg'     => 'button_active_bg',
			'--matrix-d-btn-active-text'   => 'button_active_text',
			'--matrix-d-btn-focus-ring'    => 'button_focus_ring',
			'--matrix-d-ty-primary-bg'     => 'thank_you_primary_bg',
			'--matrix-d-ty-primary-text'   => 'thank_you_primary_text',
			'--matrix-d-ty-secondary-bg'   => 'thank_you_secondary_bg',
			'--matrix-d-ty-secondary-text' => 'thank_you_secondary_text',
			'--matrix-d-ty-secondary-border' => 'thank_you_secondary_border',
		);
		foreach ( $map as $css_var => $key ) {
			if ( ! empty( $design[ $key ] ) ) {
				$vars[] = $css_var . ':' . $design[ $key ];
			}
		}

		$css = '';
		if ( ! empty( $vars ) ) {
			$css .= ':root{' . implode( ';', $vars ) . ';}';
			$css .= '.donation-btn-sr{background:var(--matrix-d-btn-bg,#fff);color:var(--matrix-d-btn-text,#00628f);border-color:var(--matrix-d-btn-border,#00628f);}';
			$css .= '.donation-btn-sr:hover{background:var(--matrix-d-btn-hover-bg,#008bcc);color:var(--matrix-d-btn-hover-text,#fff);border-color:var(--matrix-d-btn-hover-border,#87ceb7);}';
			$css .= '.donation-btn-sr.donation-btn-sr--active{background:var(--matrix-d-btn-active-bg,#008bcc);color:var(--matrix-d-btn-active-text,#fff);border-color:var(--matrix-d-btn-border,#00628f);}';
			$css .= '.donation-btn-sr:focus-visible{box-shadow:0 0 0 3px var(--matrix-d-btn-focus-ring,#1c959b);outline:none;}';
			$css .= '.matrix-donation-submit-btn{background:var(--matrix-d-btn-bg,#fff);color:var(--matrix-d-btn-text,#00628f);border-color:var(--matrix-d-btn-border,#00628f);}';
			$css .= '.matrix-donation-submit-btn:hover{background:var(--matrix-d-btn-hover-bg,#008bcc);color:var(--matrix-d-btn-hover-text,#fff);border-color:var(--matrix-d-btn-hover-border,#87ceb7);}';
			$css .= '.matrix-donation-submit-btn:active{background:var(--matrix-d-btn-active-bg,#008bcc);color:var(--matrix-d-btn-active-text,#fff);}';
			$css .= '.matrix-donation-submit-btn:focus-visible{box-shadow:0 0 0 3px var(--matrix-d-btn-focus-ring,#1c959b);outline:none;}';
			$css .= '.matrix-thank-you-btn-primary{background:var(--matrix-d-ty-primary-bg,#059ded)!important;color:var(--matrix-d-ty-primary-text,#fff)!important;}';
			$css .= '.matrix-thank-you-btn-secondary{background:var(--matrix-d-ty-secondary-bg,#fff)!important;color:var(--matrix-d-ty-secondary-text,#00628f)!important;border-color:var(--matrix-d-ty-secondary-border,#00628f)!important;}';
		}

		$custom_css = trim( (string) $design['custom_css'] );
		if ( '' !== $custom_css ) {
			$css .= "\n" . $custom_css;
		}
		return $css;
	}

	/**
	 * Shorten long Stripe references for readable admin tables.
	 *
	 * @param string $value Full Stripe reference.
	 * @return string
	 */
	private function shorten_reference( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( strlen( $value ) <= 22 ) {
			return $value;
		}
		return substr( $value, 0, 10 ) . '...' . substr( $value, -10 );
	}

	public function admin_scripts( $hook ) {
		$allowed_hooks = array(
			$this->settings_page_hook,
			'toplevel_page_matrix-donations',
			'matrix-donations_page_matrix-donations-logs',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'matrix-donations-admin',
			MATRIX_DONATIONS_PLUGIN_URL . 'assets/js/admin-media.js',
			array( 'jquery' ),
			MATRIX_DONATIONS_VERSION,
			true
		);
	}

	public static function get_options() {
		$defaults = array(
			'stripe_mode'                  => 'test',
			'stripe_test_secret_key'       => '',
			'stripe_live_secret_key'       => '',
			'stripe_test_publishable_key'  => '',
			'stripe_live_publishable_key'  => '',
			'stripe_test_webhook_secret'   => '',
			'stripe_live_webhook_secret'   => '',
			'stripe_secret_key'            => '', // Legacy.
			'stripe_publishable_key'       => '', // Legacy.
			'salesforce_url'               => '',
			'salesforce_org_id'            => '',
			'page_single'                  => 0,
			'page_monthly'                 => 0,
			'page_membership'              => 0,
			'page_renew_membership'        => 0,
			'checkout_page_id'             => 0,
			'success_page_id'              => 0,
			'cancel_page_id'               => 0,
			'debug_enabled'                => '0',
			'mock_checkout_enabled'        => '0',
			'checkout_tax_enabled'         => '1',
			'checkout_tax_percentage'      => 20,
			'technical_alerts_enabled'     => '0',
			'technical_alert_emails'       => '',
			'e2e_github_repository'        => '',
			'e2e_github_workflow'          => 'donations-e2e.yml',
			'e2e_github_ref'               => 'main',
			'e2e_github_token'             => '',
			'testing_cards_enabled'        => '1',
			'testing_cards_reference'      => "Use Stripe test mode only.\nCard: 4242 4242 4242 4242\nExpiry: any future date\nCVC: any 3 digits\nPostcode: any value",
			'design_enable_custom_styles'  => '0',
			'design_button_bg'             => '#ffffff',
			'design_button_text'           => '#00628f',
			'design_button_border'         => '#00628f',
			'design_button_hover_bg'       => '#008bcc',
			'design_button_hover_text'     => '#ffffff',
			'design_button_hover_border'   => '#87ceb7',
			'design_button_active_bg'      => '#008bcc',
			'design_button_active_text'    => '#ffffff',
			'design_button_focus_ring'     => '#1c959b',
			'design_thank_you_primary_bg'  => '#059ded',
			'design_thank_you_primary_text'=> '#ffffff',
			'design_thank_you_secondary_bg'=> '#ffffff',
			'design_thank_you_secondary_text' => '#00628f',
			'design_thank_you_secondary_border' => '#00628f',
			'design_custom_css'            => '',
			'content_heading'              => '',
			'content_description'          => '',
			'content_details'              => '',
			'content_info'                 => '',
			'content_see_more_url'         => '',
			'content_see_more_title'       => __( 'See more', 'matrix-donations' ),
			'content_see_more_target'      => '',
			'content_feature_image'        => 0,
			'content_banner'               => 0,
			'content_form_info_message'    => __( 'A monthly gift helps us respond to urgent threats and plan ahead', 'matrix-donations' ),
			'content_form_impact_message'  => __( 'Your donation could help {count} people!', 'matrix-donations' ),
			'content_form_impact_enabled'  => '1',
			'notification_admin_emails'    => sanitize_email( get_option( 'admin_email' ) ),
			'notification_admin_subject'   => __( 'New donation on {site_name}', 'matrix-donations' ),
			'notification_admin_body'      => "A donation has been received.\n\nName: {full_name}\nEmail: {email}\nType: {donation_type}\nAmount: {amount_with_currency}\nDate: {date}\nSite: {site_url}",
			'notification_user_subject'    => __( 'Thank you for your donation to {site_name}', 'matrix-donations' ),
			'notification_user_body'       => "Hi {first_name},\n\nThank you for your donation of {amount_with_currency}.\nYour support helps us continue our work.\n\nBest regards,\n{site_name}",
			'notification_test_recipient'  => sanitize_email( get_option( 'admin_email' ) ),
			'thank_you_heading_line_1'     => __( 'Thank you for your', 'matrix-donations' ),
			'thank_you_heading_line_2'     => __( 'generous donation,', 'matrix-donations' ),
			'thank_you_heading_line_3'     => __( '{first_name}!', 'matrix-donations' ),
			'thank_you_note'               => __( 'Personal note wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis.', 'matrix-donations' ),
			'thank_you_primary_label'      => __( 'How we use donations', 'matrix-donations' ),
			'thank_you_primary_url'        => home_url( '/how-we-use-donations/' ),
			'thank_you_secondary_label'    => __( 'Go to home', 'matrix-donations' ),
			'thank_you_secondary_url'      => home_url( '/' ),
			'thank_you_image'              => 0,
		);
		$opts = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $opts, $defaults );
	}

	public static function get( $key ) {
		$opts = self::get_options();
		return $opts[ $key ] ?? null;
	}

	public static function get_mode() {
		$mode = self::get( 'stripe_mode' );
		return in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';
	}

	public static function get_active_stripe_secret_key() {
		$mode = self::get_mode();
		$candidates = array(
			self::get( 'stripe_' . $mode . '_secret_key' ),
			self::get( 'test' === $mode ? 'stripe_live_secret_key' : 'stripe_test_secret_key' ),
			self::get( 'stripe_secret_key' ),
		);

		foreach ( $candidates as $candidate ) {
			$normalized = self::normalize_stripe_key( $candidate, $mode );
			if ( ! empty( $normalized ) ) {
				return $normalized;
			}
		}
		return '';
	}

	public static function get_active_stripe_publishable_key() {
		$mode = self::get_mode();
		$candidates = array(
			self::get( 'stripe_' . $mode . '_publishable_key' ),
			self::get( 'test' === $mode ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key' ),
			self::get( 'stripe_publishable_key' ),
		);

		foreach ( $candidates as $candidate ) {
			$normalized = self::normalize_stripe_key( $candidate, $mode );
			if ( ! empty( $normalized ) ) {
				return $normalized;
			}
		}
		return '';
	}

	public static function get_webhook_secrets() {
		$opts = self::get_options();
		$secrets = array_filter(
			array(
				$opts['stripe_test_webhook_secret'] ?? '',
				$opts['stripe_live_webhook_secret'] ?? '',
			)
		);
		return array_values( $secrets );
	}

	public static function is_debug_enabled() {
		return '1' === (string) self::get( 'debug_enabled' );
	}

	public static function is_mock_checkout_enabled() {
		return '1' === (string) self::get( 'mock_checkout_enabled' );
	}

	public static function is_tax_enabled() {
		return '1' === (string) self::get( 'checkout_tax_enabled' );
	}

	public static function is_technical_alerts_enabled() {
		return '1' === (string) self::get( 'technical_alerts_enabled' );
	}

	public static function get_tax_percentage() {
		$percentage = (float) self::get( 'checkout_tax_percentage' );
		if ( $percentage < 0 ) {
			return 0.0;
		}
		if ( $percentage > 100 ) {
			return 100.0;
		}
		return $percentage;
	}

	public static function get_donation_page_ids() {
		$opts = self::get_options();
		return array(
			'single'           => absint( $opts['page_single'] ?? 0 ),
			'monthly'          => absint( $opts['page_monthly'] ?? 0 ),
			'membership'       => absint( $opts['page_membership'] ?? 0 ),
			'renew_membership' => absint( $opts['page_renew_membership'] ?? 0 ),
			'checkout'         => absint( $opts['checkout_page_id'] ?? 0 ),
		);
	}

	public static function get_checkout_url() {
		$success_page_id = self::get_success_page_id();
		if ( $success_page_id ) {
			return get_permalink( $success_page_id );
		}
		return home_url( '/success/' );
	}

	public static function get_success_url() {
		$page_id = self::get_success_page_id();
		if ( $page_id ) {
			return add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', get_permalink( $page_id ) );
		}
		return home_url( '/success/?session_id={CHECKOUT_SESSION_ID}' );
	}

	/**
	 * Resolve success page ID with fallback slugs.
	 *
	 * @return int
	 */
	public static function get_success_page_id() {
		$page_id = absint( self::get( 'success_page_id' ) );
		if ( $page_id ) {
			return $page_id;
		}
		$success_page = get_page_by_path( 'success' );
		if ( $success_page && ! empty( $success_page->ID ) ) {
			return absint( $success_page->ID );
		}
		$thank_you_page = get_page_by_path( 'thank-you' );
		if ( $thank_you_page && ! empty( $thank_you_page->ID ) ) {
			return absint( $thank_you_page->ID );
		}
		return 0;
	}

	public static function get_cancel_url() {
		$page_id = absint( self::get( 'cancel_page_id' ) );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}
		return home_url( '/cancel/' );
	}

	public static function get_salesforce_form_url() {
		$url = self::get( 'salesforce_url' );
		$oid = self::get( 'salesforce_org_id' );
		if ( $url && $oid ) {
			return add_query_arg( 'orgId', $oid, $url );
		}
		return '';
	}

	public static function get_salesforce_org_id() {
		return self::get( 'salesforce_org_id' );
	}

	public static function get_donation_content() {
		$opts       = self::get_options();
		$banner_id  = absint( $opts['content_banner'] ?? 0 );
		$feature_id = absint( $opts['content_feature_image'] ?? 0 );
		return array(
			'heading'            => $opts['content_heading'] ?? '',
			'description'        => $opts['content_description'] ?? '',
			'details'            => $opts['content_details'] ?? '',
			'info'               => $opts['content_info'] ?? '',
			'see_more_link'      => array(
				'url'    => $opts['content_see_more_url'] ?? '',
				'title'  => $opts['content_see_more_title'] ?? __( 'See more', 'matrix-donations' ),
				'target' => $opts['content_see_more_target'] ?? '',
			),
			'feature_image'      => $feature_id ? array(
				'url' => wp_get_attachment_image_url( $feature_id, 'full' ),
				'alt' => get_post_meta( $feature_id, '_wp_attachment_image_alt', true ),
			) : array(),
			'banner'             => $banner_id ? array(
				'url' => wp_get_attachment_image_url( $banner_id, 'full' ),
				'alt' => get_post_meta( $banner_id, '_wp_attachment_image_alt', true ),
			) : array(),
			'form_info_message'  => $opts['content_form_info_message'] ?? __( 'A monthly gift helps us respond to urgent threats and plan ahead', 'matrix-donations' ),
			'form_impact_enabled'=> '1' === (string) ( $opts['content_form_impact_enabled'] ?? '1' ),
			'form_impact_message'=> $opts['content_form_impact_message'] ?? __( 'Your donation could help {count} people!', 'matrix-donations' ),
		);
	}

	public static function get_banner_image() {
		$content = self::get_donation_content();
		return $content['banner'];
	}

	/**
	 * Extract a Stripe key from text, optionally matching mode.
	 *
	 * @param string $raw_key Raw key input.
	 * @param string $mode    Optional mode constraint (test/live).
	 * @return string
	 */
	private static function normalize_stripe_key( $raw_key, $mode = '' ) {
		$raw_key = trim( (string) $raw_key );
		if ( '' === $raw_key ) {
			return '';
		}

		if ( preg_match_all( '/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9_]+\b/', $raw_key, $matches ) ) {
			$keys = $matches[0];
			if ( 'test' === $mode || 'live' === $mode ) {
				foreach ( $keys as $key ) {
					if ( 0 === strpos( $key, 'sk_' . $mode . '_' ) || 0 === strpos( $key, 'rk_' . $mode . '_' ) ) {
						return $key;
					}
				}
				return '';
			}
			return $keys[0];
		}

		return $raw_key;
	}

	/**
	 * Get thank-you page content options.
	 *
	 * @return array
	 */
	public static function get_thank_you_content() {
		$opts      = self::get_options();
		$image_id  = absint( $opts['thank_you_image'] ?? 0 );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$image_alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
		if ( empty( $image_url ) ) {
			$fallback_banner_id = absint( $opts['content_banner'] ?? 0 );
			if ( $fallback_banner_id ) {
				$image_url = wp_get_attachment_image_url( $fallback_banner_id, 'full' );
				if ( empty( $image_alt ) ) {
					$image_alt = get_post_meta( $fallback_banner_id, '_wp_attachment_image_alt', true );
				}
			}
		}

		return array(
			'line_1'          => $opts['thank_you_heading_line_1'] ?? __( 'Thank you for your', 'matrix-donations' ),
			'line_2'          => $opts['thank_you_heading_line_2'] ?? __( 'generous donation,', 'matrix-donations' ),
			'line_3'          => $opts['thank_you_heading_line_3'] ?? __( '{first_name}!', 'matrix-donations' ),
			'note'            => $opts['thank_you_note'] ?? '',
			'primary_label'   => $opts['thank_you_primary_label'] ?? __( 'How we use donations', 'matrix-donations' ),
			'primary_url'     => $opts['thank_you_primary_url'] ?? home_url( '/' ),
			'secondary_label' => $opts['thank_you_secondary_label'] ?? __( 'Go to home', 'matrix-donations' ),
			'secondary_url'   => $opts['thank_you_secondary_url'] ?? home_url( '/' ),
			'image_url'       => $image_url,
			'image_alt'       => $image_alt,
		);
	}
}
