<?php
/**
 * Main donation form template - loads the appropriate form by type.
 *
 * @package Matrix_Donations
 * @var string $type Donation type: single, monthly, membership, renew_membership, healthcare_membership
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="donation_form">
	<?php
	$type = isset( $type ) ? $type : 'single';
	switch ( $type ) {
		case 'single':
		case 'monthly':
			matrix_donations()->get_template( 'single-monthly', array( 'type' => $type ) );
			break;
		case 'membership':
			$donation_type       = 'membership';
			$existing_vs_new     = 'new';
			$notice_field_label  = __( 'Are you an existing member of Diabetes Ireland?*', 'matrix-donations' );
			$renew_page_id       = Matrix_Donations_Settings::get( 'page_renew_membership' ) ?: 231;
			$notice_content      = '<strong>' . __( 'If not, please complete the form below.', 'matrix-donations' ) . '</strong> ' . sprintf(
				__( 'To renew your existing membership, please <a href="%s">click here</a>', 'matrix-donations' ),
				esc_url( get_permalink( $renew_page_id ) )
			);
			matrix_donations()->get_template( 'membership', compact( 'donation_type', 'existing_vs_new', 'notice_field_label', 'notice_content' ) );
			break;
		case 'healthcare_membership':
			$donation_type       = 'membership';
			$existing_vs_new     = 'new';
			$notice_field_label  = '';
			$notice_content      = '';
			matrix_donations()->get_template( 'membership', compact( 'donation_type', 'existing_vs_new', 'notice_field_label', 'notice_content' ) );
			break;
		case 'renew_membership':
			$donation_type       = 'renew_membership';
			$existing_vs_new     = 'existing';
			$notice_field_label  = __( 'Are you an existing member of Diabetes Ireland?*', 'matrix-donations' );
			$membership_page_id   = Matrix_Donations_Settings::get( 'page_membership' ) ?: 228;
			$notice_content      = '<strong>' . __( 'If yes, please complete the form below.', 'matrix-donations' ) . '</strong> ' . sprintf(
				__( 'If you have not joined our community yet, please complete the form following <a href="%s">THIS LINK</a> and join Diabetes Ireland Community Network Programmes!', 'matrix-donations' ),
				esc_url( get_permalink( $membership_page_id ) )
			);
			matrix_donations()->get_template( 'membership', compact( 'donation_type', 'existing_vs_new', 'notice_field_label', 'notice_content' ) );
			break;
	}
	?>
</div>
