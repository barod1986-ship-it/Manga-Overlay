import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: 'editor-shell.spec.ts',
  timeout: 30_000,
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: 'http://127.0.0.1:4176',
    trace: 'retain-on-failure',
  },
  webServer: process.env.MOL_EXTERNAL_EDITOR_SERVER ? undefined : {
    command: 'node ../../../../node_modules/vite/bin/vite.js --host 127.0.0.1 --port 4176 --strictPort',
    url: 'http://127.0.0.1:4176/tests/editor-shell-fixture.html',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],
});
