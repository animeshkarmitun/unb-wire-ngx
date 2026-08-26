# Live Test Runbook — Reload + Smoke Before Commit

> **Mandatory Rule:** Passing unit tests alone is **insufficient** when runtime code changes.
> Clear compiled caches and smoke affected routes/roles **before committing**.

---

## 1. When This Runbook Applies

| Modified Files | Reload + Live Smoke Required? |
|----------------|-------------------------------|
| `app/Filament/**` (Resources, Pages, Widgets) | **Yes — Mandatory** |
| `app/Http/Controllers/**` (API or Portal) | **Yes — Mandatory** |
| `app/Models/**` (Relationships, scopes, observers) | **Yes — Mandatory** |
| `routes/**` | **Yes — Mandatory** (`route:clear`) |
| `config/**`, `.env`, `composer.json` | **Yes — Mandatory** (`config:clear`) |
| `database/migrations/**`, seeders | **Yes — Mandatory** (`migrate:fresh --seed`) |
| `resources/views/**` (Blade views) | **Yes — Mandatory** (`view:clear`) |
| Docs only, unit tests only | No |

---

## 2. Cache Reload Procedure

Run the cache invalidation command suite:
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan filament:cache-components
```

---

## 3. Smoke Test Steps

1. **Application Health:** Hit `GET /` or `GET /api/v1/health` → verify 200 OK without errors in `storage/logs/laravel.log`.
2. **Editorial Panel Auth:** Log in at `/admin/login` as relevant role (Super Admin, Chief Editor, Desk Editor, Reporter).
3. **Resource Verification:** Verify listing, create form, validation errors, and edit forms load cleanly.
4. **Wire API Delivery:** Call affected `GET /api/v1/wire/*` endpoints with subscriber bearer tokens. Verify JSON structure and pagination.
5. **State Transitions:** Verify article publishing, revision snapshot creation, and cache invalidation.

---

## 4. Completion Notes Record

In the task's **Completion Notes**, record:
- Caches cleared.
- Exact routes and roles tested.
- Observed results.
*(Missing live-smoke documentation on runtime changes will trigger a 🔴 Blocker during review).*
