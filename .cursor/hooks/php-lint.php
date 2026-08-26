<?php

/**
 * afterFileEdit: run `php -l` on edited PHP files.
 * Always exits 0 so the agent loop is not crashed; failures are recorded in state.
 */

declare(strict_types=1);

require __DIR__.'/_state.php';

$input = hooks_read_stdin_json();
$filePath = (string) ($input['file_path'] ?? '');

if ($filePath === '' || ! hooks_should_lint($filePath)) {
    exit(0);
}

$state = hooks_load_state();

if (hooks_is_runtime_php($filePath)) {
    $normalized = str_replace('\\', '/', $filePath);
    if (! in_array($normalized, $state['edited_runtime_php'], true)) {
        $state['edited_runtime_php'][] = $normalized;
    }
}

$cmd = 'php -l '.escapeshellarg($filePath).' 2>&1';
$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);
$joined = implode("\n", $output);

if ($exitCode !== 0) {
    $normalized = str_replace('\\', '/', $filePath);
    if (! in_array($normalized, $state['lint_failures'], true)) {
        $state['lint_failures'][] = $normalized;
    }
    file_put_contents(
        hooks_state_dir().DIRECTORY_SEPARATOR.'last-lint-error.txt',
        $normalized."\n".$joined."\n"
    );
    fwrite(STDERR, "[unb-wire-hooks] php -l failed: {$normalized}\n{$joined}\n");
} else {
    $normalized = str_replace('\\', '/', $filePath);
    $state['lint_failures'] = array_values(array_filter(
        $state['lint_failures'],
        static fn (string $path): bool => $path !== $normalized
    ));
}

hooks_save_state($state);

exit(0);
