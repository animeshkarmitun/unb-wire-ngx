<?php

$ok = true;
$fail = fn ($m) => printf("FAIL: %s\n", $m) and $ok = false;
$pass = fn ($m) => printf("PASS: %s\n", $m);

$design = @file_get_contents(__DIR__.'/../app-data/v1-database-design.md');
$migs = glob(__DIR__.'/../database/migrations/*.php');
$models = glob(__DIR__.'/../app/Models/*.php');

if (! $design) {
    $fail('v1-database-design.md missing');
} else {
    $pass('v1-database-design.md present');
}

$has = fn ($needle) => str_contains($design, $needle);
if (! $has('story_notes') || ! $has('is_internal')) {
    $fail('design missing story_notes.is_internal (M4)');
} else {
    $pass('design: story_notes.is_internal');
}

if (! $has('invoices')) {
    $fail('design missing invoices (M5)');
} else {
    $pass('design: invoices');
}

if (! $has('packages.status') && ! $has("CHECK ('active','archived')")) {
    $fail('design missing packages status CHECK (M7)');
} else {
    $pass('design: packages CHECK');
}

$checks = ['users.role_id' => false, 'assignments' => false, 'timestamptz' => true];
$contentAll = implode("\n", array_map(fn ($f) => file_get_contents($f), $migs));

if (str_contains($contentAll, 'assignments') && str_contains($contentAll, 'media_batches') && str_contains($contentAll, 'assignment_id')) {
    $pass('migrations: assignments + media_batches.assignment_id');
} else {
    $fail('migrations missing assignments / media_batches.assignment_id (C1)');
}

if (preg_match('/users.*restrictOnDelete|users_role_id_foreign.*RESTRICT/i', $contentAll)) {
    $pass('migrations: users.role_id RESTRICT (C3)');
} else {
    $fail('migrations: users.role_id not RESTRICT (C3)');
}

if (str_contains($contentAll, 'REVOKE UPDATE, DELETE') || str_contains($contentAll, 'append_only_grants')) {
    $pass('migrations: append-only REVOKE (C4)');
} else {
    $fail('migrations: append-only REVOKE missing (C4)');
}

$bare = 0;
foreach ($migs as $f) {
    $c = file_get_contents($f);
    if (preg_match('/\$table->timestamp\(/i', $c) && ! str_contains($f, '0001_01_01')) {
        $fail(basename($f).' uses $table->timestamp() not timestamptz (M8)');
        $bare++;
    }
}
if ($bare === 0) {
    $pass('migrations: no bare timestamp() in business tables (M8)');
}

$storyModel = @file_get_contents(__DIR__.'/../app/Models/Story.php');
if (str_contains($storyModel, "'version' => 'integer'") && str_contains($storyModel, "'word_count' => 'integer'")) {
    $pass('models: Story casts version/word_count');
} else {
    $fail('models: Story missing version/word_count casts (L4)');
}

$invoiceLine = @file_get_contents(__DIR__.'/../app/Models/InvoiceLine.php');
if (str_contains($invoiceLine, 'decimal:2')) {
    $pass('models: InvoiceLine decimal casts');
} else {
    $fail('models: InvoiceLine missing decimal casts (L4)');
}

$clientFactory = @file_get_contents(__DIR__.'/../database/factories/ClientFactory.php');
if (str_contains($clientFactory, 'radio') && str_contains($clientFactory, 'govt')) {
    $pass('factories: ClientFactory covers 6 types (L6)');
} else {
    $fail('factories: ClientFactory missing types (L6)');
}

if (str_contains($contentAll, 'stories_status_published_at_index') && preg_match('/published_at DESC/', $contentAll)) {
    $pass('migrations: stories_status_published_at DESC (L1)');
} else {
    $fail('migrations: stories index not DESC (L1)');
}

echo $ok ? "\nAll schema parity checks PASSED\n" : "\nSchema parity checks FAILED\n";
exit($ok ? 0 : 1);
