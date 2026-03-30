const { test, expect } = require('@playwright/test');

test('monthly donate page posts checkout intent and redirects', async ({ page }) => {
  await page.goto('/donate-monthly/');
  const passwordInput = page.getByRole('textbox', { name: 'Password' });
  if (await passwordInput.isVisible().catch(() => false)) {
    const sitePassword = process.env.MATRIX_DONATIONS_SITE_PASSWORD || '';
    if (!sitePassword) {
      test.skip(true, 'Site password required. Set MATRIX_DONATIONS_SITE_PASSWORD to run this test.');
    }
    await passwordInput.fill(sitePassword);
    await page.getByRole('button', { name: 'Log In' }).click();
  }

  await page.locator('#email').fill('monthly-flow@example.com');
  await page.locator('#first_name').fill('Monthly');
  await page.locator('#last_name').fill('Tester');
  const intentRequestPromise = page.waitForRequest('**/wp-json/matrix-donations/v1/checkout-intent');
  await page.route('**/wp-json/matrix-donations/v1/checkout-intent', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, checkoutUrl: '/thank-you/?session_id=mock_monthly' })
    });
  });

  await page.locator('#salesforceForm button[type="submit"]').click();
  await intentRequestPromise;
  await expect(page).toHaveURL(/\/thank-you\/\?session_id=mock_monthly$/);
});
