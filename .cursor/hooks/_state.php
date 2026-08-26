<?php

/**
 * Shared state helpers for UNB Wire Cursor hooks.
 * State lives in .cursor/hooks/state/ (gitignored).
 */

declare(strict_types=1);

function hooks_state_dir(): string
{
    $dir = __DIR__.DIRECTORY_SEPARATOR.'state';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function hooks_state_path(): string
{
    return hooks_state_dir().DIRECTORY_SEPARATOR.'session.json';
}

/**
 * @return array{edited_runtime_php: list<string>, lint_failures: list<string>, tests_ran: bool}
 */
function hooks_load_state(): array
{
    $path = hooks_state_path();
    $defaults = [
        'edited_runtime_php' => [],
        'lint_failures' => [],
        'tests_ran' => false,
    ];

    if (! is_file($path)) {
        return $defaults;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        return $defaults;
    }

    return array_merge($defaults, $decoded);
}

/**
 * @param  array{edited_runtime_php?: list<string>, lint_failures?: list<string>, tests_ran?: bool}  $state
 */
function hooks_save_state(array $state): void
{
    file_put_contents(
        hooks_state_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
    );
}

function hooks_read_stdin_json(): array
{
    $raw = stream_get_contents(STDIN);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function hooks_project_root(): string
{
    return dirname(__DIR__, 2);
}

function hooks_is_runtime_php(string $absolutePath): bool
{
    $root = hooks_project_root();
    $normalized = str_replace('\\', '/', $absolutePath);
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

    if (! str_starts_with($normalized, $rootNorm.'/')) {
        return false;
    }

    $relative = substr($normalized, strlen($rootNorm) + 1);

    foreach (['vendor/', 'storage/', 'node_modules/', '.cursor/hooks/state/'] as $skip) {
        if (str_starts_with($relative, $skip)) {
            return false;
        }
    }

    if (! str_ends_with(strtolower($relative), '.php')) {
        return false;
    }

    foreach (['app/', 'routes/', 'database/', 'resources/'] as $prefix) {
        if (str_starts_with($relative, $prefix)) {
            return true;
        }
    }

    return false;
}

function hooks_should_lint(string $absolutePath): bool
{
    $root = hooks_project_root();
    $normalized = str_replace('\\', '/', $absolutePath);
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

    if (! str_starts_with($normalized, $rootNorm.'/')) {
        return false;
    }

    $relative = substr($normalized, strlen($rootNorm) + 1);

    foreach (['vendor/', 'storage/', 'node_modules/'] as $skip) {
        if (str_starts_with($relative, $skip)) {
            return false;
        }
    }

    return str_ends_with(strtolower($relative), '.php');
}
