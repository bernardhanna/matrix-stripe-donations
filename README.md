# Matrix Donations

WordPress plugin for secure Stripe donation checkout flows. **Fully standalone** - works on any site without theme dependencies.

## Features

- **Donation types**: Single and Monthly (secure Stripe-first flow)
- **Secure checkout intent endpoint**: Nonce-protected server-side session creation
- **Stripe webhook verification**: Signed webhook validation for payment truth
- **Donation records in WordPress**: Donor email/name, amount, status, Stripe IDs
- **Top-level admin menu**: Matrix Donations > Donations, Settings, Logs
- **Sandbox/Live mode**: Independent API key sets per mode
- **Mock checkout mode**: Lets developers test flow without Stripe credentials
- **Auto-setup**: Creates required pages on activation

## Installation

1. Upload the `matrix-donations` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory (for Stripe PHP library)
3. Activate the plugin in WordPress admin

**On activation**, the plugin automatically creates these pages if they don't exist:
- **Checkout** (slug: `checkout`) – required for payment redirect
- **Donate** (slug: `donate`) – single donation form
- **Donate Monthly** (slug: `donate-monthly`)
- **Membership** (slug: `membership`)
- **Renew Membership** (slug: `renew-membership`)

4. Go to **Matrix Donations > Settings** and configure:
   - Stripe Mode (Sandbox/Live)
   - Stripe Secret/Publishable keys for both modes
   - Webhook signing secret(s)
   - Donation and redirect pages
   - Donation content (heading, description, images)

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[donate type="single"]` | Donation form. Types: `single`, `monthly`, `membership`, `renew_membership`, `healthcare_membership` |
| `[donate_box]` | Donation promo content block (heading, description, image). Use in any theme. |

## Theme Integration (optional)

To use `donate_box` in a flexible/page builder layout, add:

```php
case 'donate_box':
    if ( function_exists( 'matrix_donations' ) ) {
        matrix_donations()->render_donate_box();
    }
    break;
```

Or simply use the `[donate_box]` shortcode anywhere.

## Secure Flow

1. User fills donation form on your site
2. Form posts to a secure WordPress endpoint with nonce validation
3. Plugin creates a Stripe Checkout Session server-side and redirects to Stripe
4. Stripe sends signed webhook events to the plugin endpoint
5. Plugin stores donation status and donor/payment metadata in WordPress

## Webhook URL

Configure this in Stripe:

`/wp-json/matrix-donations/v1/webhook`

Use your test signing secret in Sandbox mode and your live signing secret in Live mode.

## Requirements

- PHP 7.4+
- WordPress 5.8+
- Stripe account

## Automated Browser Tests (Playwright)

From plugin directory:

1. `npm install`
2. `npx playwright install`
3. `npm run test:e2e`

Optional base URL:

`MATRIX_DONATIONS_BASE_URL=http://localhost:10014 npm run test:e2e`

If your local site is password-protected, provide:

`MATRIX_DONATIONS_SITE_PASSWORD=your_password npm run test:e2e`
