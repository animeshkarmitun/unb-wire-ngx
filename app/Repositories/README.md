# Repositories — UNB Wire

The ONLY layer that touches Eloquent/query-builder. Per [NFR §16.1](../../app-data/v1-non-functional-requirements.md#L600-L629).

---

## Ownership

| Repository owns | Repository does NOT own |
|-----------------|-------------------------|
| All Eloquent / query-builder calls | Business logic, state machines |
| Eager-loading definitions | Caching (`CacheAside` → service layer) |
| Partition-aware date-bound filters | Authorization (controller/Livewire) |
| Read-replica routing hints | Transactions (`DB::transaction` → service layer) |
| Cursor/offset pagination | Event dispatching |

---

## Rules

1. **Concrete classes only.** No `RepositoryInterface` + `EloquentRepository` pairs. Interfaces only where a real swap exists (e.g., `SearchService`).
2. **No caching.** Caching lives in the service layer (`CacheAside`).
3. **No business logic.** Repositories return data; services decide what it means.
4. **No transactions.** `DB::transaction()` lives in the service layer — services own atomicity.
5. **Eager-load by default.** Every method that returns models must specify its eager-loads. No lazy-loading in dev/test (`Model::preventLazyLoading`).
6. **Partition-aware.** Methods on partitioned tables (`deliveries`, `audit_logs`) require a date range — no unbounded queries.
7. **Read-replica hints.** Read methods use `DB::connection('pgsql::read')` (stubbed until PostgreSQL migration; currently uses default connection).

---

## Naming

- One repository per aggregate root: `StoryRepository`, `ClientRepository`, etc.
- File: `app/Repositories/{Aggregate}Repository.php`
- Class: `App\Repositories\{Aggregate}Repository`
- Methods: `find*`, `create`, `update`, `delete`, `sync*`, `paginate*`, `count*`

---

## Injection

Repositories are injected via constructor injection in services, Livewire components, and controllers. Laravel's container resolves them automatically — no binding needed for concrete classes.

```php
// In a service
public function __construct(private StoryRepository $stories) {}

// In a Livewire component
public function __construct(private StoryRepository $stories) {}
```

---

## Inventory

| Repository | Aggregate | Phase |
|------------|-----------|-------|
| `StoryRepository` | Story + Version + Event + Note | 1 |
| `ClientRepository` | Client + ApiKey + Subscription + Channel | 2 |
| `DeliveryRepository` | Delivery + Attempt | 3 |
| `MediaRepository` | MediaAsset + Derivative + Batch + Review | 3 |
| `PackageRepository` | Package + ClientPackage | 4 |
| `RoleRepository` | Role + Permission + User (staff) | 4 |
| `SettingRepository` | Setting | 5 |
| `AuditLogRepository` | AuditLog | 5 |
| `IndexOutboxRepository` | IndexOutbox | 5 |
| `CategoryRepository` | Category | 5 |
