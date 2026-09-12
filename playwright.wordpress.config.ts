import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
  testDir: './tests/wordpress/e2e', fullyParallel: false, workers: 1, retries: 0,
  reporter: [['list']],
  use: { baseURL: 'http://localhost:8080', screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  projects: [
    { name: 'reader-desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1050 } } },
    { name: 'reader-phone', use: { ...devices['Pixel 7'] } },
  ],
});
