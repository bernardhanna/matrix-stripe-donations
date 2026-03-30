<?php
/**
 * Donation flow header (replaces navbar on donation pages).
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$step    = Matrix_Donations::get_donation_flow_step();
$theme_logo_id = get_theme_mod( 'custom_logo' );
$acf_logo_id   = function_exists( 'get_field' ) ? get_field( 'logo', 'option' ) : 0;
$logo_id       = $theme_logo_id ?: $acf_logo_id;
$logo_url      = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
$logo_alt      = $logo_id ? ( get_post_meta( $logo_id, '_wp_attachment_image_alt', true ) ?: get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
$step_1_active  = $step === 1;
$step_2_active  = false;
?>
<header class="matrix-donation-steps-header" role="banner">
	<div class="matrix-donation-steps-inner">
		<div class="matrix-donation-steps-logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="matrix-donation-steps-logo-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> - <?php esc_attr_e( 'Go to homepage', 'matrix-donations' ); ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" class="matrix-donation-steps-logo-image" />
				<?php else : ?>
					<div class="matrix-donation-steps-logo-fallback" role="img" aria-label="<?php esc_attr_e( 'Company logo', 'matrix-donations' ); ?>">
						<span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
					</div>
				<?php endif; ?>
			</a>
		</div>

		<nav class="matrix-donation-steps-nav" role="navigation" aria-label="<?php esc_attr_e( 'Donation process steps', 'matrix-donations' ); ?>">
			<div role="progressbar"
				aria-valuenow="<?php echo $step_1_active ? '1' : '0'; ?>"
				aria-valuemin="1"
				aria-valuemax="2"
				aria-label="<?php echo $step_1_active ? esc_attr__( 'Step 1 of 2: Your donation - current step', 'matrix-donations' ) : esc_attr__( 'Step 1 of 2: Your donation - completed', 'matrix-donations' ); ?>"
				class="matrix-donation-step matrix-donation-step-active">
				<div class="matrix-donation-step-dot" aria-hidden="true">
					<span>1</span>
				</div>
				<span class="matrix-donation-step-label"><?php esc_html_e( 'Your donation', 'matrix-donations' ); ?></span>
			</div>

			<div class="matrix-donation-step-separator" aria-hidden="true"></div>

			<div role="progressbar"
				aria-valuenow="<?php echo $step_2_active ? '2' : '0'; ?>"
				aria-valuemin="1"
				aria-valuemax="2"
				aria-label="<?php esc_attr_e( 'Step 2 of 2: Your details', 'matrix-donations' ); ?>"
				class="matrix-donation-step matrix-donation-step-inactive">
				<div class="matrix-donation-step-dot" aria-hidden="true">
					<span>2</span>
				</div>
				<span class="matrix-donation-step-label"><?php esc_html_e( 'Your details', 'matrix-donations' ); ?></span>
			</div>
		</nav>
	</div>

	<div class="sr-only">
		<p>
			<?php
			if ( $step === 1 ) {
				esc_html_e( 'Donation process: Step 1 of 2. Currently on "Your donation" step. Next step is "Your details" on secure Stripe checkout.', 'matrix-donations' );
			}
			?>
		</p>
	</div>
</header>
