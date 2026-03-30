<?php
/**
 * Template Part: Donation Content
 * Used in page builder / flex layouts as donate_box block.
 * Content comes from Settings > Matrix Donations > Donation Content (replaces ACF).
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content = Matrix_Donations_Settings::get_donation_content();

$heading       = $content['heading'];
$description   = $content['description'];
$details       = $content['details'];
$info_text     = $content['info'];
$see_more_link = $content['see_more_link'];
$feature_image = $content['feature_image'];

$see_url    = ! empty( $see_more_link['url'] ) ? $see_more_link['url'] : '';
$see_title  = ! empty( $see_more_link['title'] ) ? $see_more_link['title'] : __( 'See more', 'matrix-donations' );
$see_target = ! empty( $see_more_link['target'] ) ? $see_more_link['target'] : '';
?>
<div class="donation-content">
	<section class="lead-form__styled" aria-labelledby="donation-title">
		<?php if ( $heading ) : ?>
			<h1 id="donation-title"><?php echo esc_html( $heading ); ?></h1>
		<?php endif; ?>

		<?php if ( $description ) : ?>
			<div class="form-intro">
				<?php echo wp_kses_post( $description ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $details ) : ?>
			<div class="donation-note">
				<?php echo wp_kses_post( $details ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $info_text || $see_url ) : ?>
			<p class="form-note-ways">
				<?php echo esc_html( $info_text ); ?>
				<?php if ( $see_url ) : ?>
					<a href="<?php echo esc_url( $see_url ); ?>" <?php echo $see_target ? 'target="' . esc_attr( $see_target ) . '" rel="noopener"' : ''; ?>>
						<?php echo esc_html( $see_title ); ?>
					</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $feature_image['url'] ) ) : ?>
		<div class="donation-hero-image">
			<img src="<?php echo esc_url( $feature_image['url'] ); ?>" alt="<?php echo esc_attr( $feature_image['alt'] ?? '' ); ?>" loading="lazy" />
		</div>
	<?php endif; ?>
</div>
