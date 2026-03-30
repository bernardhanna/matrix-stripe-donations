const { test, expect } = require('@playwright/test');

async function unlockIfProtected(page) {
  const passwordInput = page.getByRole('textbox', { name: 'Password' });
  if (await passwordInput.isVisible().catch(() => false)) {
    const sitePassword = process.env.MATRIX_DONATIONS_SITE_PASSWORD || '';
    if (!sitePassword) {
      test.skip(true, 'Site password required. Set MATRIX_DONATIONS_SITE_PASSWORD to run this test.');
    }
    await passwordInput.fill(sitePassword);
    await page.getByRole('button', { name: 'Log In' }).click();
  }
}

test('donate form includes key accessibility attributes', async ({ page }) => {
  await page.goto('/donate/');
  await unlockIfProtected(page);

  await expect(page.locator('#custom-amount')).toHaveAttribute('aria-describedby', 'custom-amount-error');
  await expect(page.locator('#email')).toHaveAttribute('aria-describedby', 'email-error');
  await expect(page.locator('#first_name')).toHaveAttribute('aria-describedby', 'name-error');
  await expect(page.locator('#last_name')).toHaveAttribute('aria-describedby', 'surname-error');
  await expect(page.locator('#checkout-status')).toHaveAttribute('role', 'status');
  await expect(page.locator('#checkout-status')).toHaveAttribute('aria-live', 'polite');
});

test('donation option buttons keep pressed state semantics', async ({ page }) => {
  await page.goto('/donate-monthly/');
  await unlockIfProtected(page);

  const singleBtn = page.locator('#single-btn');
  const monthlyBtn = page.locator('#monthly-btn');
  await expect(monthlyBtn).toHaveAttribute('aria-pressed', 'true');
  await singleBtn.click();
  await expect(singleBtn).toHaveAttribute('aria-pressed', 'true');
  await expect(monthlyBtn).toHaveAttribute('aria-pressed', 'false');
});
