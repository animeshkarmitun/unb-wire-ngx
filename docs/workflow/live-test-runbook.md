# Live Test Runbook — Smoke before commit

Completed 2026-08-28 for M7:
- config:clear, route:clear, view:clear executed
- php artisan test 65 passed
- php artisan monitor:outbox-lag OK lag=0s
- Routes smoked: /admin (rbac 403), /api/v1/portal/feed cached, /api/v1/feed client.api scoped
- Horizon queues: default,outbox,fanout,derivatives,billing with snapshot every 5m
