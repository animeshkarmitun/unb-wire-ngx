# AI Context-Bounded Task Decomposition Protocol (ACT-DP)
## UNB Wire | Context Engineering & Task Decomposition Standard

---

## 1. Purpose

ACT-DP establishes the standard for breaking complex editorial and wire service features into **atomic, context-bounded tasks** before generating code.

**The Goal:** Prevent LLM hallucinations, avoid broken cross-file dependencies, ensure testability, and keep prompt context within safe token budgets.

---

## 2. Core Principles

| Principle | Rule | Rationale |
|-----------|------|-----------|
| **Single Responsibility** | One task = one endpoint, migration, or focused service method | Keeps diffs surgical and reviewable |
| **Context Budget** | Max 3,000 tokens of input context per prompt | Leaves headroom for reasoning and output |
| **Contract-First** | Inputs, outputs, validation rules, and status transitions defined first | Eliminates speculative coding |
| **Test-Bound** | Every task must have explicit test criteria | Clear definition of done |
| **Traceability** | Every task references an ADR or data model specification | Prevents unauthorized architectural drift |

---

## 3. The 3-Layer Decomposition Structure

Every task file created in `docs/tasks/` must follow the 3-layer pattern:

```
Layer 1: The Contract (What)
├── Input parameters & Form Request validation rules
├── Output DTO, JSON Resource shape, or Blade view
├── Error codes & HTTP status mappings (401, 403, 422, 429, 500)
└── Authorization policy checks

Layer 2: The Logic (How)
├── Business rules, editorial transitions, or wire feed algorithms
├── Database query patterns & eager loading requirements
├── Events emitted, webhooks queued, or notifications dispatched
└── Transaction boundaries (`DB::transaction`)

Layer 3: The Context (Where)
├── Target files to create or modify
├── Reference interfaces, models, and policy files
├── Test cases to verify
└── Smoke test routes
```

---

## 4. Standard Task File Template

Save as `docs/tasks/<PREFIX>-<NNN>-<short-slug>.md`:

```markdown
# Task: [TASK-ID] — [Title]

**Status:** ⏳ Pending | 🔄 In Progress | ✅ Completed | ⚠️ Needs Fix
**Dependencies:** [None or prior Task ID]
**Parent ADR:** [DEC-NNN or feature plan link]

---

## 1. Contract (What)
- **Inputs / Validation:** [FormRequest or parameter types]
- **Outputs / Response:** [Resource DTO / status code]
- **Authorization:** [Policy method e.g., `can('publish', $article)`]

---

## 2. Logic (How)
1. [Step 1: Validate request]
2. [Step 2: Database transaction & model updates]
3. [Step 3: Dispatch events / queue webhooks]
4. [Step 4: Return response]

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Http/Controllers/...`
  - `app/Models/...`
  - `tests/Feature/...`
- **Reference Files:**
  - `docs/knowledge-inventory/domain.md`

---

## 4. Prompt (For the Coding AI)
> [Copy-pasteable self-contained instructions for coder]

---

## 5. Test Criteria
- [ ] Feature test covering happy path
- [ ] Validation failure test (422)
- [ ] Unauthorized access test (403)
- [ ] `php -l` clean on all modified files

---

## 6. Completion Notes
- **Shipped:** [Summary of changes]
- **Tests:** [Command & result]
- **Live Smoke:** [Routes and roles tested]
- **Review:** [Review report path]

---

## 7. Prompt Ready?
- [x] Yes
```
