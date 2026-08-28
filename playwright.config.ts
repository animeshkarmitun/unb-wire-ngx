import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  webServer: [
    { command: 'php artisan serve --port=8000', port: 8000, reuseExistingServer: true },
  ],
  use: { baseURL: 'http://localhost:8000', trace: 'on-first-retry' },
});
