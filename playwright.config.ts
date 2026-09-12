import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e', fullyParallel: false, retries: process.env.CI ? 1 : 0,
  workers: 1, reporter: [['list'], ['html', { open: 'never' }]],
  use: { baseURL: 'http://127.0.0.1:5173', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1050 } } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 1050 } } },
    { name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 1050 } } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'], defaultBrowserType: 'chromium' } },
    { name: 'mobile-webkit', use: { ...devices['iPhone 13'], defaultBrowserType: 'webkit' } },
  ],
  webServer: { command: 'node node_modules/vite/bin/vite.js wp-content/plugins/manga-overlay-core --host 127.0.0.1', url: 'http://127.0.0.1:5173', reuseExistingServer: !process.env.CI, timeout: 60000 },
});
