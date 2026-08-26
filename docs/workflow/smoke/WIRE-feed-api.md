# Smoke Checklist: WIRE — Feed & Subscriber API Delivery

> Verify REST API output, token validation, rate limits, and cache invalidation.

---

## Steps

1. **Subscriber Token Authentication:**
   - Call `GET /api/v1/wire/latest` without `Authorization` header → verify `401 Unauthorized`.
   - Call with invalid bearer token → verify `401 Unauthorized`.
   - Call with valid subscriber token → verify `200 OK` with JSON array of stories.

2. **Category & Priority Filtering:**
   - Call `GET /api/v1/wire/latest?category=national` → verify all returned items belong to 'national'.
   - Call `GET /api/v1/wire/latest?priority=breaking` → verify only breaking news dispatches returned.

3. **Cache Invalidation:**
   - Call `GET /api/v1/wire/latest` (response cached).
   - Publish a new article via editorial panel.
   - Call `GET /api/v1/wire/latest` again → verify new article immediately appears at top of feed.

4. **Tier Scoping:**
   - Call media download endpoint with standard subscriber token → verify rate limits or 403 on raw assets.
   - Call with enterprise token → verify signed asset URL generated.
