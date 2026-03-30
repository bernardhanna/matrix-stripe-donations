<?php
/**
 * Membership and Renew Membership form template.
 *
 * @package Matrix_Donations
 * @var string $donation_type
 * @var string $existing_vs_new
 * @var string $notice_field_label
 * @var string $notice_content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salesforce_url = Matrix_Donations_Settings::get_salesforce_form_url();
$org_id         = Matrix_Donations_Settings::get_salesforce_org_id();
$checkout_url   = Matrix_Donations_Settings::get_checkout_url();
$type           = isset( $donation_type ) ? $donation_type : 'membership';
$existing_vs_new = isset( $existing_vs_new ) ? $existing_vs_new : 'new';
$notice_field_label = isset( $notice_field_label ) ? $notice_field_label : '';
$notice_content    = isset( $notice_content ) ? $notice_content : '';
?>
<form action="<?php echo esc_url( $salesforce_url ); ?>" method="POST" id="salesforceForm" class="salesformForm_membership">
	<input type="hidden" name="oid" value="<?php echo esc_attr( $org_id ); ?>" />
	<input type="hidden" name="retURL" id="redirect_url" value="<?php echo esc_url( $checkout_url ); ?>">
	<input type="hidden" name="url" value="" />
	<input type="hidden" name="lead_source" value="Web" />
	<input type="hidden" name="recordType" value="SEE COMMENT" />

	<?php if ( $type !== 'healthcare_membership' ) : ?>
		<fieldset class="existing_vs_new"><div class="label"><?php echo esc_html( $notice_field_label ); ?></div>
			<label for="existing">
				<input type="radio" name="donation_type" id="existing" value="renew_membership" <?php checked( $existing_vs_new, 'existing' ); ?>><?php esc_html_e( 'Yes', 'matrix-donations' ); ?>
			</label>
			<label for="new">
				<input type="radio" name="donation_type" id="new" value="membership" <?php checked( $existing_vs_new, 'new' ); ?>><?php esc_html_e( 'No', 'matrix-donations' ); ?>
			</label>
		</fieldset>
	<?php endif; ?>

	<div class="notice">
		<?php echo wp_kses_post( $notice_content ); ?>
	</div>

	<fieldset class="form-group half_half">
		<div class="col">
			<label for="first_name"><?php esc_html_e( 'First Name*', 'matrix-donations' ); ?></label>
			<input id="first_name" maxlength="40" name="first_name" size="20" type="text" required />
		</div>
		<div class="col">
			<label for="last_name"><?php esc_html_e( 'Last Name*', 'matrix-donations' ); ?></label>
			<input id="last_name" maxlength="80" name="last_name" size="20" type="text" required />
		</div>
	</fieldset>

	<fieldset class="form-group half_half">
		<?php if ( $type === 'renew_membership' ) : ?>
			<div class="col">
				<label for="membership_number"><?php esc_html_e( 'Membership number', 'matrix-donations' ); ?></label>
				<input id="membership_number" name="membership_number" size="12" type="text" />
			</div>
		<?php endif; ?>
		<?php if ( $type === 'membership' ) : ?>
			<div class="col">
				<label for="00NRz000001cwP0"><?php esc_html_e( 'Date of birth', 'matrix-donations' ); ?></label>
				<span class="dateInput dateOnlyInput"><input id="00NRz000001cwP0" name="00NRz000001cwP0" size="12" type="date" max="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-1 day' ) ) ); ?>" /></span>
			</div>
			<input type="text" id="is_minor" name="is_minor" style="display:none;"/>
		<?php endif; ?>
		<div class="col">
			<label for="email"><?php esc_html_e( 'Email address*', 'matrix-donations' ); ?></label>
			<input id="email" maxlength="80" name="email" size="20" type="text" required />
		</div>
	</fieldset>

	<?php if ( $type === 'membership' ) : ?>
		<fieldset class="form-group half_half" id="minor_fields" style="display:none;">
			<div class="col">
				<label for="g_first_name"><?php esc_html_e( 'Parent/Guardian First Name*', 'matrix-donations' ); ?></label>
				<input id="g_first_name" maxlength="40" name="g_first_name" size="20" type="text" />
			</div>
			<div class="col">
				<label for="g_last_name"><?php esc_html_e( 'Parent/Guardian Last Name*', 'matrix-donations' ); ?></label>
				<input id="g_last_name" maxlength="80" name="g_last_name" size="20" type="text" />
			</div>
		</fieldset>
	<?php endif; ?>

	<fieldset class="form-group half_half">
		<div class="col">
			<label for="street"><?php esc_html_e( 'Street*', 'matrix-donations' ); ?></label>
			<input id="street" maxlength="40" name="street" size="20" type="text" required />
		</div>
		<div class="col">
			<label for="city"><?php esc_html_e( 'City*', 'matrix-donations' ); ?></label>
			<input id="city" maxlength="40" name="city" size="20" type="text" required />
		</div>
	</fieldset>

	<fieldset class="form-group half_half">
		<div class="col">
			<label for="state"><?php esc_html_e( 'County*', 'matrix-donations' ); ?></label>
			<input id="state" maxlength="20" name="state" size="20" type="text" required />
		</div>
		<div class="col">
			<label for="zip"><?php esc_html_e( 'Eircode', 'matrix-donations' ); ?><?php echo $existing_vs_new !== 'new' ? '*' : ''; ?></label>
			<input id="zip" maxlength="20" name="zip" size="20" type="text" <?php echo $existing_vs_new !== 'new' ? 'required' : ''; ?> />
		</div>
	</fieldset>

	<fieldset class="form-group half_half">
		<div class="col">
			<label for="00NQ1000005zUBB"><?php esc_html_e( 'GeoCode', 'matrix-donations' ); ?></label>
			<select id="00NQ1000005zUBB" name="00NQ1000005zUBB" title="GeoCode">
				<option value="">--None--</option>
				<option value="Abroad">Abroad</option>
				<option value="All">All</option>
				<option value="Carlow">Carlow</option>
				<option value="Cavan">Cavan</option>
				<option value="Clare">Clare</option>
				<option value="Cork">Cork</option>
				<option value="Donegal">Donegal</option>
				<option value="Dublin County">Dublin County</option>
				<option value="Dublin North">Dublin North</option>
				<option value="Dublin South">Dublin South</option>
				<option value="Galway">Galway</option>
				<option value="Kerry">Kerry</option>
				<option value="Kildare">Kildare</option>
				<option value="Kilkenny">Kilkenny</option>
				<option value="Laois">Laois</option>
				<option value="Leitrim">Leitrim</option>
				<option value="Limerick">Limerick</option>
				<option value="Longford">Longford</option>
				<option value="Louth">Louth</option>
				<option value="Mayo">Mayo</option>
				<option value="Meath">Meath</option>
				<option value="Monaghan">Monaghan</option>
				<option value="Offaly">Offaly</option>
				<option value="Roscommon">Roscommon</option>
				<option value="Sligo">Sligo</option>
				<option value="Tipperary North">Tipperary North</option>
				<option value="Tipperary South">Tipperary South</option>
				<option value="Ulster">Ulster</option>
				<option value="United Kindom">United Kindom</option>
				<option value="Unknown">Unknown</option>
				<option value="Waterford">Waterford</option>
				<option value="Westmeath">Westmeath</option>
				<option value="Wexford">Wexford</option>
				<option value="Wicklow">Wicklow</option>
			</select>
		</div>
	</fieldset>

	<fieldset class="form-group half_half">
		<div class="col">
			<label for="mobile"><?php esc_html_e( 'Mobile', 'matrix-donations' ); ?></label>
			<input id="mobile" maxlength="40" name="mobile" size="20" type="text" />
		</div>
		<?php if ( $type === 'membership' ) : ?>
			<div class="col">
				<label for="00NRz000001d5bx"><?php esc_html_e( 'Type of Diabetes', 'matrix-donations' ); ?></label>
				<select id="00NRz000001d5bx" name="00NRz000001d5bx" title="Type of Diabetes">
					<option value="">--None--</option>
					<option value="1">1</option>
					<option value="2">2</option>
					<option value="Pre-diabetes">Pre-diabetes</option>
					<option value="Gestational">Gestational</option>
					<option value="No Diabetes">No Diabetes</option>
					<option value="Unknown">Unknown</option>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $type !== 'membership' ) : ?>
			<div class="col">
				<label for="donation_amount"><?php esc_html_e( 'Membership status', 'matrix-donations' ); ?></label>
				<select id="donation_amount" name="donation_amount" title="Type of Diabetes">
					<option value="30">Concessionary Senior (€30)</option>
					<option value="30">Concessionary Student (€30)</option>
					<option value="45">Family (€45)</option>
					<option value="40">Individual (€40)</option>
					<option value="60" data-pro="true">Healthcare Professional (€60)</option>
					<option value="60">Member and Draw Concessionary Senior (€60)</option>
					<option value="60">Member and Draw Concessionary Student (€60)</option>
					<option value="60">Member and Draw Family (€60)</option>
					<option value="60">Member and Draw Individual (€60)</option>
				</select>
			</div>
		<?php endif; ?>
	</fieldset>

	<?php if ( $type === 'membership' ) : ?>
		<fieldset class="form-group half_half">
			<div class="col">
				<label for="donation_amount"><?php esc_html_e( 'Membership status', 'matrix-donations' ); ?></label>
				<select id="donation_amount" name="donation_amount" title="Membership status">
					<option value="60">Community Network Programme (€60)</option>
					<option value="60">Healthcare Professional (€60)</option>
				</select>
			</div>
			<div class="col">
				<label for="00NRz000007YYsr"><?php esc_html_e( 'What type of Community Network would you like to join?', 'matrix-donations' ); ?></label>
				<select id="00NRz000007YYsr" name="00NRz000007YYsr" title="Community">
					<option value="family">Family Community Network Programme</option>
					<option value="type_1">Type 1 Community Network Programme</option>
					<option value="type_2">Type 2 Community Network Programme</option>
					<option value="healthcare" data-pro="true">Healthcare Professional Community Network</option>
				</select>
			</div>
			<div class="col">
				<label for="frequency"><?php esc_html_e( 'Frequency', 'matrix-donations' ); ?></label>
				<select id="frequency" name="frequency" title="Community">
					<option value="monthly">Monthly (12x €5)</option>
					<option value="quarterly">Quarterly (4x €15)</option>
					<option value="biannual">Biannual (2x €30)</option>
					<option value="annual">Annual (1x €60)</option>
				</select>
			</div>
		</fieldset>
	<?php endif; ?>

	<div id="show_pro" style="display:none;">
		<div class="notice">
			<?php esc_html_e( 'If you are a Healthcare Professional please complete the information below', 'matrix-donations' ); ?><br />
			<?php esc_html_e( 'If you choose Other as Professional Status please fill in your Occupation in the box provided.', 'matrix-donations' ); ?>
		</div>
		<fieldset class="form-group half_half">
			<div class="col">
				<label for="00NQ1000005zWGD"><?php esc_html_e( 'Professional status', 'matrix-donations' ); ?></label>
				<select id="00NQ1000005zWGD" name="00NQ1000005zWGD" title="Professional Member">
					<option value=""><?php esc_html_e( "I'm not a healthcare professional", 'matrix-donations' ); ?></option>
					<option value="Clinical Nurse Specialist">Clinical Nurse Specialist</option>
					<option value="Clinical Nurse Specialist Diabetes">Clinical Nurse Specialist Diabetes</option>
					<option value="Counsellor">Counsellor</option>
					<option value="Dietitian - Adult Diabetes">Dietitian - Adult Diabetes</option>
					<option value="Dietitian - Paeds Diabetes">Dietitian - Paeds Diabetes</option>
					<option value="Dietitian - Pregnancy Midwife">Dietitian - Pregnancy Midwife</option>
					<option value="Medic - Adult Diabetes">Medic - Adult Diabetes</option>
					<option value="Medic - Consultant">Medic - Consultant</option>
					<option value="Medic - General">Medic - General</option>
					<option value="Medic - GP">Medic - GP</option>
					<option value="Medic - Non Consultant">Medic - Non Consultant</option>
					<option value="Medic - Paeds Diabetes">Medic - Paeds Diabetes</option>
					<option value="Nurse - Adult Diabetes">Nurse - Adult Diabetes</option>
					<option value="Nurse - Community Nurse">Nurse - Community Nurse</option>
					<option value="Nurse - General">Nurse - General</option>
					<option value="Nurse - Paeds Diabetes">Nurse - Paeds Diabetes</option>
					<option value="Nurse - Practice Nurse">Nurse - Practice Nurse</option>
					<option value="Nurse - Pregnancy">Nurse - Pregnancy</option>
					<option value="Nurse - Residents Home">Nurse - Residents Home</option>
					<option value="Pharmacist">Pharmacist</option>
					<option value="Podiatrist - Community">Podiatrist - Community</option>
					<option value="Podiatrist - Hospital">Podiatrist - Hospital</option>
					<option value="Psychologist">Psychologist</option>
					<option value="Public Health Non Nursing">Public Health Non Nursing</option>
					<option value="Researcher">Researcher</option>
					<option value="Other">Other</option>
				</select>
			</div>
			<div class="col">
				<label for="00NQ1000005zWHp"><?php esc_html_e( 'Occupation', 'matrix-donations' ); ?></label>
				<input id="00NQ1000005zWHp" maxlength="255" name="00NQ1000005zWHp" size="20" type="text" />
			</div>
		</fieldset>
	</div>

	<fieldset>
		<label for="terms"><input type="checkbox" name="terms" id="terms" required><?php esc_html_e( 'By submitting this form you agree with the', 'matrix-donations' ); ?> <a href="" target="_blank"><?php esc_html_e( 'Terms and Conditions', 'matrix-donations' ); ?></a> <?php esc_html_e( 'and', 'matrix-donations' ); ?> <a href="" target="_blank"><?php esc_html_e( 'Privacy Policy', 'matrix-donations' ); ?></a>.</label>
	</fieldset>

	<?php if ( $type === 'membership' ) : ?>
		<h3><?php esc_html_e( 'Keeping in touch with you', 'matrix-donations' ); ?></h3>
		<p><?php esc_html_e( 'We would love to keep you updated via our monthly newsletter on breaking news, upcoming events, products & services, and the different ways you can support us, including financial support. Communication via email may also include occasional invitations to upcoming events such as educational webinars, workshops and conferences. It is really important to us that you have the best support possible and we provide you with relevant and timely information about our services and events. If you are happy to receive emails from us, please tick the box below.', 'matrix-donations' ); ?></p>
		<fieldset>
			<label for="00NRz000001d0Md"> <input id="00NRz000001d0Md" name="00NRz000001d0Md" type="checkbox" value="1" /> <?php esc_html_e( 'Please tick the box to tell us you are happy to receive emails.', 'matrix-donations' ); ?> </label>
		</fieldset>
	<?php endif; ?>

	<input type="submit" name="submit" value="<?php esc_attr_e( 'Proceed to payment', 'matrix-donations' ); ?>">
</form>
