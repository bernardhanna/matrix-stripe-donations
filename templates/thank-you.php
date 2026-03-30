<?php
/**
 * Donation success / thank-you template.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$donation_record = isset( $donation_record ) && is_array( $donation_record ) ? $donation_record : array();
$content         = Matrix_Donations_Settings::get_thank_you_content();
$first_name      = ! empty( $donation_record['donor_first_name'] ) ? sanitize_text_field( $donation_record['donor_first_name'] ) : '';
if ( '' === $first_name && ! empty( $donation_record['metadata'] ) ) {
	$metadata = json_decode( (string) $donation_record['metadata'], true );
	if ( is_array( $metadata ) && ! empty( $metadata['first_name'] ) ) {
		$first_name = sanitize_text_field( $metadata['first_name'] );
	}
}
if ( '' === $first_name ) {
	$query_first_name = isset( $_GET['donor_first_name'] ) ? sanitize_text_field( wp_unslash( $_GET['donor_first_name'] ) ) : '';
	if ( '' !== $query_first_name ) {
		$first_name = $query_first_name;
	}
}
if ( '' === $first_name ) {
	$first_name = __( 'Friend', 'matrix-donations' );
}

$line_1 = str_replace( '{first_name}', $first_name, $content['line_1'] );
$line_2 = str_replace( '{first_name}', $first_name, $content['line_2'] );
$line_3 = str_replace( '{first_name}', $first_name, $content['line_3'] );

$fallback_image  = 'https://api.builder.io/api/v1/image/assets/f35586c581c84ecf82b6de32c55ed39e/a8ef88da99574ae3552bf8f3dbeba112d9efb6de?placeholderIfAbsent=true';
$image_url       = ! empty( $content['image_url'] ) ? $content['image_url'] : $fallback_image;
$image_alt       = ! empty( $content['image_alt'] ) ? $content['image_alt'] : __( 'Thank you illustration showing appreciation for donation', 'matrix-donations' );
?>

<section class="matrix-thank-you bg-[#EBF9FF]" aria-labelledby="matrix-thank-you-title">
	<div class="matrix-thank-you-inner">
		<div class="matrix-thank-you-grid">
			<div class="matrix-thank-you-copy">
				<header class="matrix-thank-you-title text-[var(--Blue-SR-500,#00628F)] font-primary text-[36px] leading-[44px] tracking-[-0.72px] font-bold" id="matrix-thank-you-title">
					<div><h1><?php echo esc_html( $line_1 ); ?></h1></div>
					<div><p><?php echo esc_html( $line_2 ); ?></p></div>
					<div><p><?php echo esc_html( $line_3 ); ?></p></div>
				</header>

				<div class="matrix-thank-you-note-wrap">
					<p class="matrix-thank-you-note text-[var(--Gray-800,#001929)] font-primary text-[18px] leading-[24px] font-normal"><?php echo esc_html( $content['note'] ); ?></p>

					<div class="matrix-thank-you-actions" role="group" aria-label="<?php esc_attr_e( 'Donation actions', 'matrix-donations' ); ?>">
						<a class="matrix-thank-you-btn matrix-thank-you-btn-primary" href="<?php echo esc_url( $content['primary_url'] ); ?>">
							<?php echo esc_html( $content['primary_label'] ); ?>
						</a>
						<a class="matrix-thank-you-btn matrix-thank-you-btn-secondary" href="<?php echo esc_url( $content['secondary_url'] ); ?>">
							<?php echo esc_html( $content['secondary_label'] ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="matrix-thank-you-image-wrap">
				<img
					src="<?php echo esc_url( $image_url ); ?>"
					alt="<?php echo esc_attr( $image_alt ); ?>"
					class="matrix-thank-you-image"
					loading="lazy"
				/>
			</div>
		</div>
	</div>
</section>
