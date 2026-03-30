<?php
/**
 * Single and Monthly donation form template.
 *
 * @package Matrix_Donations
 * @var string $type 'single' or 'monthly'
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type            = isset( $type ) ? $type : 'single';
$content         = Matrix_Donations_Settings::get_donation_content();
$donation_image  = $content['banner'] ?? array();
$info_message    = $content['form_info_message'] ?? __( 'A monthly gift helps us respond to urgent threats and plan ahead', 'matrix-donations' );
$impact_enabled  = ! empty( $content['form_impact_enabled'] );
$impact_template = $content['form_impact_message'] ?? __( 'Your donation could help {count} people! 😊', 'matrix-donations' );
$amounts         = array( 10, 20, 100, 250 );
?>
<main class="matrix-donation-main grid grid-cols-1 md:grid-cols-2 gap-8 items-start max-w-[1200px] mx-auto px-4 py-8 md:px-16 md:py-9<?php echo empty( $donation_image['url'] ) ? ' md:grid-cols-1' : ''; ?>">
	<section class="donation-form-section" aria-labelledby="donation-heading">
		<?php if ( Matrix_Donations_Settings::is_mock_checkout_enabled() ) : ?>
			<div class="mb-4 p-3 rounded border border-yellow-300 bg-yellow-100 text-yellow-900 text-sm" role="alert">
				<strong><?php esc_html_e( 'Mock Checkout Enabled:', 'matrix-donations' ); ?></strong>
				<?php esc_html_e( 'no real Stripe charge will be created in this mode.', 'matrix-donations' ); ?>
			</div>
		<?php endif; ?>

		<form id="salesforceForm" class="salesformForm_donation" action="#" method="post" novalidate>
			<header>
				<h1 id="donation-heading" class="matrix-donation-title">
					<?php esc_html_e( 'Your donation', 'matrix-donations' ); ?>
				</h1>
			</header>
			<input type="hidden" name="donation_type" id="donation_type" value="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="donation_amount" id="donation_amount" value="10">

			<!-- Donation Type Section -->
			<fieldset class="mt-4 max-w-full">
				<legend class="text-sm font-semibold leading-none text-slate-800" id="donation-type-legend">
					<?php esc_html_e( 'Donation type', 'matrix-donations' ); ?>
				</legend>
				<div class="flex flex-wrap gap-4 mt-1" role="group" aria-labelledby="donation-type-legend">
					<button
						type="button"
						class="matrix-donation-type-btn donation-btn-sr <?php echo $type === 'monthly' ? 'donation-btn-sr--active' : ''; ?>"
						id="monthly-btn"
						aria-pressed="<?php echo $type === 'monthly' ? 'true' : 'false'; ?>"
						data-type="monthly"
					>
						<span><?php esc_html_e( 'Monthly', 'matrix-donations' ); ?></span>
					</button>
					<button
						type="button"
						class="matrix-donation-type-btn donation-btn-sr <?php echo $type === 'single' ? 'donation-btn-sr--active' : ''; ?>"
						id="single-btn"
						aria-pressed="<?php echo $type === 'single' ? 'true' : 'false'; ?>"
						data-type="single"
					>
						<span><?php esc_html_e( 'Single', 'matrix-donations' ); ?></span>
					</button>
				</div>
			</fieldset>

			<!-- Info Message -->
			<?php if ( ! empty( $info_message ) ) : ?>
			<div class="matrix-donation-info-message" role="note" aria-live="polite">
				<p class="matrix-donation-info-message-text">
					<?php echo wp_kses_post( $info_message ); ?>
				</p>
			</div>
			<?php endif; ?>

			<!-- Amount Selection -->
			<fieldset class="mt-4 w-full">
				<legend class="text-sm font-semibold leading-none text-slate-800" id="amount-legend">
					<?php esc_html_e( 'Amount', 'matrix-donations' ); ?>
				</legend>
				<div class="flex flex-wrap gap-4 mt-1" role="group" aria-labelledby="amount-legend">
					<?php foreach ( $amounts as $amt ) : ?>
					<button
						type="button"
						class="matrix-amount-btn donation-btn-sr <?php echo $amt === 10 ? 'donation-btn-sr--active' : ''; ?>"
						data-amount="<?php echo (int) $amt; ?>"
						aria-pressed="<?php echo $amt === 10 ? 'true' : 'false'; ?>"
					>
						<span>€<?php echo (int) $amt; ?></span>
					</button>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<!-- Custom Amount Input -->
			<div class="mt-4 w-full text-base leading-none min-h-[52px] text-slate-600">
				<label for="custom-amount" class="sr-only"><?php esc_html_e( 'Enter custom donation amount', 'matrix-donations' ); ?></label>
				<input
					type="number"
					id="custom-amount"
					name="custom_amount"
					placeholder="<?php esc_attr_e( 'Or enter your own amount', 'matrix-donations' ); ?>"
					class="flex-1 w-full px-4 py-3 rounded border border-solid border-slate-400 text-slate-700 focus:border-[var(--Blue-SR-500,#00628F)] focus:ring-2 focus:ring-[var(--Blue-SR-500,#00628F)] focus:ring-opacity-20"
					min="1"
					step="0.01"
					aria-describedby="custom-amount-error"
				/>
				<div id="custom-amount-error" class="text-red-600 text-xs mt-1 hidden" role="alert"></div>
			</div>

			<!-- Personal Information -->
			<div class="mt-4 w-full">
				<!-- Email Address -->
				<div class="w-full">
					<label for="email" class="flex justify-between items-center w-full text-xs text-slate-900">
						<span class="flex-1 shrink self-stretch my-auto basis-0 text-slate-900">
							<?php esc_html_e( 'Email address', 'matrix-donations' ); ?>
						</span>
					</label>
					<input
						type="email"
						id="email"
						name="email"
						required
						autocomplete="email"
						placeholder="<?php esc_attr_e( 'Email address', 'matrix-donations' ); ?>"
						class="w-full p-4 mt-1 text-sm leading-none text-slate-700 rounded border border-solid border-slate-400 min-h-[52px] focus:border-[var(--Blue-SR-500,#00628F)] focus:ring-2 focus:ring-[var(--Blue-SR-500,#00628F)] focus:ring-opacity-20"
						aria-describedby="email-error"
					/>
					<div id="email-error" class="text-red-600 text-xs mt-1 hidden" role="alert"></div>
				</div>

				<!-- Name -->
				<div class="mt-2 w-full whitespace-nowrap">
					<label for="first_name" class="flex justify-between items-center w-full text-xs text-slate-900">
						<span class="flex-1 shrink self-stretch my-auto basis-0 text-slate-900">
							<?php esc_html_e( 'Name', 'matrix-donations' ); ?>
						</span>
					</label>
					<input
						type="text"
						id="first_name"
						name="first_name"
						required
						autocomplete="given-name"
						placeholder="<?php esc_attr_e( 'Name', 'matrix-donations' ); ?>"
						class="w-full p-4 mt-1 text-sm leading-none text-slate-700 rounded border border-solid border-slate-400 min-h-[52px] focus:border-[var(--Blue-SR-500,#00628F)] focus:ring-2 focus:ring-[var(--Blue-SR-500,#00628F)] focus:ring-opacity-20"
						aria-describedby="name-error"
					/>
					<div id="name-error" class="text-red-600 text-xs mt-1 hidden" role="alert"></div>
				</div>

				<!-- Surname -->
				<div class="mt-2 w-full whitespace-nowrap">
					<label for="last_name" class="flex justify-between items-center w-full text-xs text-slate-900">
						<span class="flex-1 shrink self-stretch my-auto basis-0 text-slate-900">
							<?php esc_html_e( 'Surname', 'matrix-donations' ); ?>
						</span>
					</label>
					<input
						type="text"
						id="last_name"
						name="last_name"
						required
						autocomplete="family-name"
						placeholder="<?php esc_attr_e( 'Surname', 'matrix-donations' ); ?>"
						class="w-full p-4 mt-1 text-sm leading-none text-slate-700 rounded border border-solid border-slate-400 min-h-[52px] focus:border-[var(--Blue-SR-500,#00628F)] focus:ring-2 focus:ring-[var(--Blue-SR-500,#00628F)] focus:ring-opacity-20"
						aria-describedby="surname-error"
					/>
					<div id="surname-error" class="text-red-600 text-xs mt-1 hidden" role="alert"></div>
				</div>
			</div>

			<!-- Impact Message -->
			<?php if ( $impact_enabled && ! empty( $impact_template ) ) : ?>
				<div class="matrix-donation-impact-message" role="status" aria-live="polite">
					<p class="matrix-donation-impact-message-text" id="impact-message" data-template="<?php echo esc_attr( $impact_template ); ?>">
						<?php echo esc_html( str_replace( '{count}', '10', $impact_template ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Submit Button -->
			<div class="pt-2 mt-4 w-full">
				<button
					type="submit"
					class="matrix-donation-submit-btn"
				>
					<span><?php esc_html_e( 'Proceed to payment page', 'matrix-donations' ); ?></span>
				</button>
			</div>
			<div id="checkout-error" class="text-red-600 text-sm mt-3 hidden" role="alert"></div>
			<div id="checkout-status" class="screen-reader-text" role="status" aria-live="polite"></div>
		</form>
	</section>

	<?php if ( ! empty( $donation_image['url'] ) ) : ?>
	<!-- Hero Image (dynamic from plugin settings) -->
	<aside class="overflow-hidden rounded-lg w-full" aria-label="<?php esc_attr_e( 'Donation impact visualization', 'matrix-donations' ); ?>">
		<img
			src="<?php echo esc_url( $donation_image['url'] ); ?>"
			alt="<?php echo esc_attr( $donation_image['alt'] ?? __( 'Visual representation of donation impact', 'matrix-donations' ) ); ?>"
			class="object-cover w-full aspect-square"
			loading="lazy"
		/>
	</aside>
	<?php endif; ?>
</main>
