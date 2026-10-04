# Task: M14-COV-008 — OpenAiProvider unit tests

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; FR-AI provider boundary

---

## 1. Contract (What)
- **Inputs / Validation:** `Http::fake` responses: 200 chat completion, 200 with JSON mode body, 429, 500, malformed JSON, missing usage.
- **Outputs / Response:** `OpenAiProvider` returns `AiResult` with text and token counts on success. Errors do not throw raw HTML into the editorial body; they surface a typed failure the existing `AiService` can record. No live API call.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Read `app/Services/Ai/OpenAiProvider.php` and `AiResult`.
2. Add `tests/Unit/OpenAiProviderTest.php` (no `RefreshDatabase`). Fake HTTP only.
3. Cover: happy path token cost, JSON mode parse, 429/500, malformed body, timeout/connection exception if the class catches it.
4. If the class has no error handling, add the minimum so a 500 does not return untrusted body text as a headline. Do not change prompt copy.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Unit/OpenAiProviderTest.php`
  - `app/Services/Ai/OpenAiProvider.php` only for error handling if missing
- **Reference Files:**
  - `app/Services/Ai/AiResult.php`
  - `tests/Feature/AiServiceTest.php` (do not duplicate kill-switch tests)

---

## 4. Prompt (For the Coding AI)
> Unit-test `OpenAiProvider` with `Http::fake`. No network. Assert token usage parsing, JSON mode, and 429/500/malformed responses. If a 500 currently becomes editorial text, stop that and test it. Do not touch `AiService` kill switch tests.

---

## 5. Test Criteria
- [ ] Zero HTTP calls leave the process (`Http::preventStrayRequests`)
- [ ] Success asserts content + usage
- [ ] 500 does not return provider HTML as the result text
- [ ] Test lives in `tests/Unit` and does not use `RefreshDatabase`

---

## 6. Completion Notes
- **Shipped:** `tests/Unit/OpenAiProviderTest.php` (no `RefreshDatabase`, `Http::preventStrayRequests`) covers: 200 success parses JSON, populates `tokensIn/tokensOut/costMicros/model`; 429 surfaces `error.message`; 500 surfaces a non-HTML error string and does not bleed the provider HTML body into the editorial result; malformed JSON body is tolerated by `parseResponse` without throwing; thrown exception becomes an `error` string starting with `AI request failed`.
- **Tests:** `php artisan test --filter=OpenAiProviderTest` 5 passed (23 assertions). `php artisan test --testsuite=Unit` green.
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
