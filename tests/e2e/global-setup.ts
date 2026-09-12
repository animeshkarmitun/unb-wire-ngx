import { execSync } from 'child_process';

export default function globalSetup() {
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
