<?php

/**
 * stop: if runtime PHP was edited without php artisan test (or lint failed), request one follow-up.
 */

declare(strict_types=1);

require __DIR__.'/_state.php';

$input = hooks_read_stdin_json();
$status = (string) ($input['status'] ?? '');
$loopCount = (int) ($input['loop_count'] ?? 0);

if ($status !== 'completed') {
    echo json_encode(new stdClass);
    exit(0);
}

$state = hooks_load_state();
$edited = is_array($state['edited_runtime_php'] ?? null) ? $state['edited_runtime_php'] : [];
$lintFailures = is_array($state['lint_failures'] ?? null) ? $state['lint_failures'] : [];
$testsRan = (bool) ($state['tests_ran'] ?? false);

$missingTests = count($edited) > 0 && ! $testsRan;
$hasLintFailures = count($lintFailures) > 0;
$needsFollowUp = $loopCount === 0 && ($missingTests || $hasLintFailures);

if (! $needsFollowUp) {
    hooks_save_state([
        'edited_runtime_php' => [],
        'lint_failures' => [],
        'tests_ran' => false,
    ]);
    echo json_encode(new stdClass);
    exit(0);
}

$lines = [];

if ($missingTests) {
    $lines[] = 'Hook gate: runtime PHP was edited this session without evidence of `php artisan test`.';
    $lines[] = 'Run the affected Pest/PHPUnit suite now (prefer a narrow `--filter` when possible), fix failures, then stop again.';
}

if ($hasLintFailures) {
    $lines[] = 'Hook gate: `php -l` failed on:';
    foreach (array_slice($lintFailures, 0, 10) as $path) {
        $lines[] = '- '.$path;
    }
    $lines[] = 'Fix syntax errors before considering the task done.';
}

if ($edited !== []) {
    $rel = [];
    $root = str_replace('\\', '/', hooks_project_root()).'/';
    foreach (array_slice($edited, 0, 15) as $path) {
        $p = str_replace('\\', '/', (string) $path);
        $rel[] = str_starts_with($p, $root) ? substr($p, strlen($root)) : $p;
    }
    $lines[] = 'Edited runtime files include: '.implode(', ', $rel);
}

echo json_encode([
    'followup_message' => implode("\n", $lines),
], JSON_UNESCAPED_SLASHES);

exit(0);
