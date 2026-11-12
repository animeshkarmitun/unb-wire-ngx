import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: 'canary-pipeline.spec.ts',
  timeout: 90000,
  retries: 0,
  use: {
    baseURL: process.env.BASE_URL || 'http://147.93.171.193:8090',
    screenshot: 'on',
    trace: 'retain-on-failure',
    headless: true,
  },
  reporter: [['list']],
});
