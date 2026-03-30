const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  use: {
    baseURL: process.env.MATRIX_DONATIONS_BASE_URL || 'http://localhost:10014',
    trace: 'on-first-retry'
  }
});
