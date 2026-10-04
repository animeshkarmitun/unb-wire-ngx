# Task: M14-COV-004 — One entitlement compiler

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; domain.md §3 (FR-DST-002, FR-DST-003)

---

## 1. Contract (What)
- **Inputs / Validation:** Client with two active packages whose filters do not individually match an `en` + category B story, but whose union does. A second client whose only package `ends_at` is past. A third whose `starts_at` is in the future.
- **Outputs / Response:** Portal feed, Meili filter, `FanoutStory`, and `SendStoryEmail` all use `EntitlementResolver`. Expired and not-yet-started subscriptions deliver nothing. Overlap unions languages, categories, and media kinds. Email does not send when the resolver denies.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Read `app/Services/Search/EntitlementResolver.php`, `app/Jobs/FanoutStory.php` (package query ~lines 50–79), `app/Jobs/SendStoryEmail.php`.
2. Fan-out must call the resolver (or one shared method the resolver also uses). Delete the private AND-per-row filter. Respect `ends_at` and do not match `starts_at` in the future. `media_kinds` stays part of the filter.
3. `SendStoryEmail::handle` returns without sending when the client is not entitled to that story. Kill notices stay on the prior-receipt path in `FanoutStory` — do not route kills through the resolver.
4. Tests in `tests/Feature/EntitlementResolverTest.php` plus `tests/Feature/FanoutAdvancedTest.php` and `tests/Feature/EmailFanoutTest.php`: union match, per-package miss that union hits, expired `ends_at` no delivery, future `starts_at` no delivery, email suppressed when denied, email sent when allowed.
5. A client must not be deliverable if the resolver would hide the story. Assert both sides in one test.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Jobs/FanoutStory.php`
  - `app/Jobs/SendStoryEmail.php`
  - `app/Services/Search/EntitlementResolver.php` (only if `starts_at` / `media_kinds` missing)
  - `tests/Feature/EntitlementResolverTest.php`
  - `tests/Feature/FanoutAdvancedTest.php`
  - `tests/Feature/EmailFanoutTest.php`
- **Reference Files:**
  - `docs/knowledge-inventory/domain.md` §3
  - `docs/testing-coverage-gaps.md` §4 "Two entitlement compilers"

---

## 4. Prompt (For the Coding AI)
> Domain rule: one `entitlement_filter` drives fan-out, portal, and search. Overlapping subscriptions union. Expiry cuts delivery. `FanoutStory` currently filters each package row with AND and ignores `ends_at`. `SendStoryEmail` ignores packages. Route both through `EntitlementResolver`. Add the union, expiry, future-start, and email-deny tests. Kill notices still go to prior recipients, not through the resolver. Do not change portal HTTP contracts.

---

## 5. Test Criteria
- [ ] Union case: story matches neither package alone and matches the union — fan-out creates a delivery
- [ ] Expired `ends_at`: no delivery and portal feed hides the story for that client
- [ ] Future `starts_at`: no delivery
- [ ] Email not sent when resolver denies
- [ ] Existing kill-notice tests still pass

---

## 6. Completion Notes
- **Shipped:** `app/Services/Search/EntitlementResolver.php` now exposes `strictEntitlement(Client)` and `clientAllowed(Client, Story)`. Strict variant returns empty arrays when there are no current subscriptions (fail-closed). Public `forClient()` keeps the legacy fallback so portal/feed compilers stay backward compatible. `app/Jobs/FanoutStory.php` queries `Client::where('status','active')` and filters through `clientAllowed`, replacing the per-row AND query (and dropping the `ends_at` / `starts_at` blindness). `app/Jobs/SendStoryEmail.php` returns early on `! clientAllowed`. New `tests/Feature/EntitlementCompilerTest.php` covers union-match, expired `ends_at`, future `starts_at`, email-denied and email-allowed. `tests/Feature/SendStoryEmailTest.php` helper now attaches an open package to its seeded client.
- **Tests:** `php artisan test --filter=EntitlementCompilerTest` 5 passed. `php artisan test` 818 passed / 1 skipped (2664 assertions). `php scripts/schema-parity-check.php` PASS.
- **Live Smoke:** `php artisan test --filter=EmailFanoutTest` green.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
