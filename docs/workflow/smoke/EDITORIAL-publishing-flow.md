# Smoke Checklist: EDITORIAL — Article Lifecycle & Publishing Flow

> Verify end-to-end editorial creation, review lock, revision history, and publication.

---

## Steps

1. **Draft Creation:**
   - Log in as Reporter.
   - Create new article with Title, Category, Lead Summary, and Body.
   - Save as Draft → verify status is `draft`.

2. **Submission:**
   - Click "Submit for Review" → status updates to `submitted`.

3. **Editorial Review & Concurrency Locking:**
   - Log in as Desk Editor in browser window A.
   - Open submitted article → verify editing lock is acquired (`locked_by_user_id` set).
   - In browser window B (logged in as second editor), attempt to edit same article → verify lock notification / read-only state.

4. **Publishing & Revision Snapshot:**
   - Editor approves and clicks "Publish Now".
   - Verify status transitions to `published` and `published_at` is set.
   - Verify `article_revisions` table receives an exact snapshot of published content.

5. **Post-Publish Edit:**
   - Edit headline of published article and save.
   - Verify second revision record is inserted with updated headline.
