## Summary

- <!-- What changed, referencing ACT-DP Task IDs e.g. [M1-CORE-001] -->

## Motivation & Domain Reference

<!-- Domain/editorial/wire rules implemented, referencing docs/knowledge-inventory/ -->

## Test Plan

- [ ] `php -l` clean on all modified files
- [ ] `php artisan test` passes
- [ ] Live smoke done per `docs/workflow/live-test-runbook.md` (routes + roles: ______)
- [ ] `php artisan migrate:fresh --seed` runs cleanly (if schema changed)
- [ ] Filament editorial panel loads for affected roles
- [ ] Wire API feeds return expected JSON schema

<!-- Only check [x] what was actually executed. -->

## Operational / Deployment Notes

<!-- Migrations, queue workers, cron schedule, .env keys — or "none" -->

## Out of Scope

<!-- Deliberately excluded items; deferred to subsequent tasks -->
