import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  retries: 1,
  globalSetup: './tests/e2e/global-setup.ts',
  webServer: [
    { command: 'php artisan serve --port=8000', port: 8000, reuseExistingServer: true },
    { command: 'npm run dev -- --port 3000', cwd: 'portal', port: 3000, reuseExistingServer: true, timeout: 60000 },
  ],
  use: { baseURL: 'http://localhost:8000', trace: 'on-first-retry', screenshot: 'only-on-failure' },
  reporter: [['list'], ['html', { open: 'never' }]],
});
