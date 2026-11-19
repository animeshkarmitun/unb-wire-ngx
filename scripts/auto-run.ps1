# auto-run.ps1 - UNB Wire task pipeline automation (Linear-driven)
# Usage: powershell -File scripts\auto-run.ps1 [-MaxTasks 40] [-DelaySeconds 20]
# Each iteration spawns a fresh opencode session that picks the highest-priority
# active Linear issue (via scripts/linear-client.cjs) and completes it end-to-end:
# plan -> gates -> smoke -> review -> commit -> PR -> merge (CI green) -> Linear Done.
#
# Skip rules (session refuses the task and labels it "Blocked"):
#   - Issues in $SkipList (product decisions / deferred infra needing owner input)
#   - Issues already labeled "Blocked"
# A session may label an issue "Blocked" itself when it hits a genuine blocker
# (missing credentials, product decision required, unresolvable failing gates);
# the runner then moves to the next issue.

param(
    [int]$MaxTasks = 40,
    [int]$DelaySeconds = 20,
    [string]$CliPath = "opencode",
    [string]$Model = "opencode-go/glm-5.3-flash",
    [string[]]$SkipIssues = @("COS-25", "COS-24", "COS-6")
)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent $PSScriptRoot
Set-Location $repo

if (-not (Get-Command $CliPath -ErrorAction SilentlyContinue)) {
    Write-Host "[X] opencode CLI not found. Install: npm install -g opencode-ai" -ForegroundColor Red
    exit 1
}

$prompt = @'
You are in a fresh session - continue the UNB Wire task pipeline.

1. Read first (in order): AGENTS.md (required reading list), then check the active
   Linear issues: `node scripts/linear-client.cjs list`.
2. Pick the next issue: highest priority (P1 > P2 > P3 > P4) among active issues,
   skipping any that appear in this skip-list: COS-25, COS-24, COS-6, or that carry
   the "Blocked" label. If NO eligible issue remains, reply with exactly
   "ALL TASKS COMPLETE" as the first line of your final message and stop.
3. Complete the chosen issue end-to-end (this standing approval applies - do NOT wait
   for owner approval between steps):
   - `node scripts/linear-client.cjs get COS-XX` for the full description
   - `node scripts/linear-client.cjs move COS-XX "In Progress"`
   - Follow the AGENTS.md mandatory workflow for the task size (trivial = surgical
     fix; non-trivial = task file -> plan -> implement -> tests)
   - Quality gates: php -l on changed files, php artisan test, pint, schema-parity
     (if schema touched), npm run build (if frontend touched)
   - Live smoke per docs/workflow/live-test-runbook.md (local routes; for server-
     touching changes use the SSH runbook patterns already in scripts/ history)
   - Review: mechanical self-review against AGENTS.md standards, note deviations
   - Git: branch (or main for docs-only), Conventional Commit with [COS-XX] tag,
     push; PR + `gh pr merge` once CI is green (docs-only commits may push to main
     directly)
   - Linear: `node scripts/linear-client.cjs comment COS-XX -` (pipe a summary via
     stdin) with deploy/verification evidence, then
     `node scripts/linear-client.cjs move COS-XX "Done"`
   - Rule: exactly ONE issue per session - finish it, then stop.
4. If the issue hits a genuine blocker (missing credentials, product decision required,
   unresolvable failing gates), do NOT fake success:
   - `node scripts/linear-client.cjs comment COS-XX -` with the blocker details
   - `node scripts/linear-client.cjs label COS-XX "Blocked"`
   - Reply "TASK BLOCKED: COS-XX" as the first line of your final message and stop.
5. Never touch One Group ERP (port 80 containers), and never skip the AGENTS.md
   quality gates to finish faster.
'@

Write-Host ""
Write-Host "[>] UNB Wire auto-run started - max $MaxTasks iterations, $DelaySeconds s pause between" -ForegroundColor Cyan

$completed = 0
$blocked = 0

for ($i = 1; $i -le $MaxTasks; $i++) {
    $before = node scripts\linear-client.cjs list 2>&1 | Out-String

    if ($before -match 'ALL TASKS COMPLETE') {
        Write-Host ""
        Write-Host "[OK] ALL TASKS COMPLETE - $completed task(s) done, $blocked blocked in this run" -ForegroundColor Green
        exit 0
    }

    Write-Host ""
    Write-Host "=== Iteration $i (completed: $completed, blocked: $blocked) ===" -ForegroundColor Cyan

    & $CliPath run $prompt -m $Model --title "auto-run task $($completed + 1)"

    $completed++
    $after = node scripts\linear-client.cjs list 2>&1 | Out-String

    # Count eligible issues remaining (non-skip, non-blocked)
    $skipPatterns = ($SkipIssues + 'Blocked') -join '|'
    $remaining = ($after -split "`n" | Where-Object { $_ -match '\] \(P[1-4] \|' -and $_ -notmatch $skipPatterns }).Count

    if ($remaining -eq 0) {
        Write-Host ""
        Write-Host "[OK] No eligible issues remain - $completed task(s) done, $blocked blocked in this run" -ForegroundColor Green
        exit 0
    }

    if ($after -eq $before) {
        Write-Host ""
        Write-Host "[!] Issue list unchanged - agent may have been blocked. Next iteration will pick the next eligible issue." -ForegroundColor Yellow
    }

    Start-Sleep -Seconds $DelaySeconds
}

Write-Host ""
Write-Host "[STOP] MaxTasks ($MaxTasks) reached. Check Linear (scripts/linear-client.cjs list) for status." -ForegroundColor Yellow
