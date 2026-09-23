# Task: M13-QA-FIX-001 — P0 gap fixes from FR cross-check

**Status:** ✅ Completed
**Dependencies:** M13-QA-001 (audit findings)
**Parent ADR:** `docs/fr-cross-check-report.md` P0 #1/#4/#5 + P1 #7; FR-DST-008, FR-CLT-001, FR-AI-008, FR-ACC-002, FR-NWS-016

---

## 1. Contract (What)
Four surgical fixes closing the small P0 gaps from the FR cross-check:
1. **Kill/correction fan-out** — `FanoutStory` no longer exits early for `killed`: kill notices (`story.killed`) fan out to every client that previously received the story (`deliveries` sent/delivered pairs, idempotent `-killed` key, no trigger filtering — prior receipt is the criterion). Updates re-deliver with `story.updated` when a prior success exists (`DeliveryRepository::hasPriorSuccess`); `StoryService::updateDraft` on a **published** story dispatches fan-out (correction path). Retry worker (`ProcessDeliveriesCommand`) derives the event per delivery instead of hardcoding `story.published`.
2. **`clients.status` gate** — publish/update fan-out only targets `clients.status = 'active'` (join in the entitlement query + email block). Kill notices bypass the gate (suspended clients still get kills for items they received).
3. **AI gate audit** — allowlist skip now logs `action=auto_published`, `payload.gate=auto`, `payload.ai_gate=skipped_auto_allowlist` (FR-AI-008's audit promise; the dead `gate='auto'` path is now reached); breaking-story bypass logs `payload.ai_gate=skipped_breaking` (FR-NTF-003).
4. **Session/device purge on deactivate** — `RolesManager::deactivateUser` deletes DB sessions and sets `devices.revoked_at` (FR-ACC-002).

---

## 2. Logic (How)
1. `FanoutStory`: `handle` branches — `killed` → `sendKillNotices` (prior `sent|delivered` (client,channel) pairs, `story.killed` payload via `WebhookPayloadBuilder`, FTP via `PushFtpDelivery`); `published` → existing entitlement flow with `clients.status='active'` + event naming via `hasPriorSuccess`. Shared `sendWebhook` helper.
2. `StoryService::transition`: `$aiGate` marker (`skipped_auto_allowlist` / `skipped_breaking`) captured in the gate block; allowAuto sets `$gate='auto'` → `auto_published` action; payload = `array_filter(['gate' => …, 'ai_gate' => …])`.
3. `StoryService::updateDraft`: after the transaction, `dispatch(new FanoutStory)` when the story is `published` (idempotency key includes `version` → one delivery per revision).
4. `ProcessDeliveriesCommand`: event = `killed ? story.killed : (hasPriorSuccess(exclude self) ? story.updated : story.published)`.
5. `RolesManager::deactivateUser`: session delete + device revoke after the status flip.

---

## 3. Context (Where)
- **Files Modified:**
  - `app/Jobs/FanoutStory.php` (kill path, status gate, event naming, `sendWebhook` helper)
  - `app/Services/StoryService.php` (gate audit markers, updateDraft fan-out)
  - `app/Console/Commands/ProcessDeliveriesCommand.php` (event-aware retry)
  - `app/Livewire/Admin/RolesManager.php` (session/device purge)
  - `app/Repositories/DeliveryRepository.php` (`hasPriorSuccess`)
  - `tests/Feature/FanoutAdvancedTest.php`, `tests/Feature/AddNewsTest.php`, `tests/Feature/RolesManagerTest.php`
- **Reference Files:** `docs/fr-cross-check-report.md`, `app/Services/Delivery/WebhookPayloadBuilder.php`

---

## 4. Test Criteria
- [x] Kill notice fans out to prior recipients (event `story.killed`, idempotent); killed story without prior delivery gets no notice (replaces `test_killed_story_no_fanout`)
- [x] Suspended client gets no publish/update delivery
- [x] Update fan-out emits `story.updated` when prior success exists; `updateDraft` on published dispatches fan-out
- [x] Allowlist skip audited (`auto_published` + `ai_gate=skipped_auto_allowlist`); breaking bypass audited (`ai_gate=skipped_breaking`)
- [x] Deactivate purges sessions + revokes devices
- [x] Full `php artisan test` green · `php -l` clean · schema parity PASSED

---

## 5. Completion Notes
- **Shipped:** All four fixes as specified in §1/§2. `FanoutStory` webhook sending deduplicated into `sendWebhook` (used by both publish and kill paths). Kill idempotency key suffixes `-killed` so a kill never collides with the publish key for the same version. Email channel kill notices and media-after-publish fan-out remain out of scope (noted in the report).
- **Tests:** 6 new + 1 rewritten (`FanoutAdvancedTest` 9 total, `AddNewsTest` +2, `RolesManagerTest` +1) — full suite **736 passed, 1 skipped, 0 failures**; `php -l` clean on 5 changed PHP files; parity PASSED (no schema change).
- **Live Smoke:** `optimize:clear`; `monitor:outbox-lag` OK lag=0s; `/api/v1/portal/feed` 200; `/admin` guest 302→/login. Server stopped after smoke.
- **Review:** PR.
