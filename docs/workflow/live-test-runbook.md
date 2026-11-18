# Live Test Runbook — Smoke before commit

Completed 2026-08-28 for M7:
- config:clear, route:clear, view:clear executed
- php artisan test 65 passed
- php artisan monitor:outbox-lag OK lag=0s
- Routes smoked: /admin (rbac 403), /api/v1/portal/feed cached, /api/v1/feed client.api scoped
- Horizon queues: default,outbox,fanout,derivatives,billing with snapshot every 5m

Completed 2026-09-06 for M8 (Milestone 8 Full Regression Pass):
- `php artisan optimize:clear` executed (config, cache, compiled, events, routes, views, blade-icons cleared)
- `php artisan test`: 215 passed (801 assertions), 0 failures
- `npx playwright test --workers=2`: 128 passed, 0 failures, 1 skipped across all 22 specs
- `php scripts/schema-parity-check.php`: all checks PASSED
- `npm run build`: built production assets cleanly
- `php artisan monitor:outbox-lag`: OK
- Verified Livewire modal wire:ignore isolation, RBAC negative route 403s (maria@unbnews.org, arif@unbnews.org), and English News header link tolerance.

Completed 2026-11-18 for COS-74 (portal auth + media privatization):
- optimize:clear executed; php artisan test: 993 passed / 6 skipped / 0 failed
- Routes smoked (php artisan serve :8011): /api/v1/portal/feed|context|search-token unauth = 401; with client API key = 200 (entitlement-filtered); /api/v1/media/{id}/download -> signed /api/v1/media/{id}/file = 200 image/jpeg; unsigned file route = 403; /api/v1/portal/story/{id}/download = 200
- php artisan media:privatize moved 222 files off public disk; /storage/media/... = 404 afterwards
- schema-parity-check: PASSED; migrate:fresh --seed: clean

