import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/browser',
  fullyParallel: false,
  workers: 1,
  timeout: 60000,
  use: {
    baseURL: process.env.HMS_BASE_URL || 'http://127.0.0.1:8000',
    browserName: 'chromium',
    channel: process.platform === 'win32' ? 'chrome' : undefined,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
