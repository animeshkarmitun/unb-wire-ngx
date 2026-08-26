<?php

/**
 * afterShellExecution: mark tests_ran when artisan test / phpunit ran.
 */

declare(strict_types=1);

require __DIR__.'/_state.php';

$input = hooks_read_stdin_json();
$command = (string) ($input['command'] ?? '');

if ($command === '') {
    exit(0);
}

$looksLikeTests = (bool) preg_match(
    '/artisan\s+test\b|\\\\phpunit|phpunit(?:\.phar)?\b|vendor[\\\\\/]bin[\\\\\/]phpunit\b/i',
    $command
);

if (! $looksLikeTests) {
    exit(0);
}

$state = hooks_load_state();
$state['tests_ran'] = true;
hooks_save_state($state);

exit(0);
