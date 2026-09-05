import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: 'reader.e2e.ts',
  fullyParallel: true,
  timeout: 20_000,
  projects: [
    {
      name: 'chromium-1440',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'firefox-768',
      use: { ...devices['Desktop Firefox'], viewport: { width: 768, height: 900 } },
    },
    {
      name: 'webkit-mobile-360',
      use: { ...devices['iPhone 13'], viewport: { width: 360, height: 800 } },
    },
  ],
});
