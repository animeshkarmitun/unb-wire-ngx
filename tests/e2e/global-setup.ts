import { execSync } from 'child_process';

export default function globalSetup() {
  // The GitHub Actions e2e job already runs 'php artisan migrate:fresh --seed'
  // before invoking Playwright. Re-running it here after the webServer has
  // started would drop the connections the server holds and replace them with
  // freshly-seeded tables the server has not reconnected to. Skip the duplicate
  // when CI already did the work.
  if (process.env.CI) {
    console.log('[global-setup] CI detected — skipping migrate:fresh --seed.');
    return;
  }

  console.log('[global-setup] Running migrate:fresh --seed...');
  try {
    execSync('php artisan migrate:fresh --seed', {
      stdio: 'inherit',
      timeout: 120000,
    });
    console.log('[global-setup] Database seeded successfully.');
  } catch (e) {
    console.error('[global-setup] Seeding failed:', e);
    throw e;
  }
}
