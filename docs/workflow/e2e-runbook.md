# End-to-End (E2E) Testing Runbook — UNB Wire

> Protocol for browser-level and full-stack automated testing of critical user journeys.

---

## 1. Critical User Journeys Covered

1. **Reporter to Published Story:**
   - Reporter logs in → creates Draft article → attaches image → submits for review.
   - Desk Editor logs in → acquires lock → edits/approves → publishes immediately.
   - Subscriber API consumer calls `GET /api/v1/wire/latest` → verifies story is present in feed with correct category.

2. **Breaking News Alert Distribution:**
   - Editor publishes story with `priority = 'breaking'`.
   - Webhook worker fires → payload dispatched to subscriber test webhook endpoint.

3. **Subscriber Tier Access Gates:**
   - Standard subscriber token attempts to fetch enterprise media asset → receives 403 Forbidden.
   - Enterprise subscriber token calls same asset → receives 200 OK signed download link.

---

## 2. Test Execution

Using Pest / Laravel Dusk / Playwright:
```bash
php artisan test --group=e2e
```
