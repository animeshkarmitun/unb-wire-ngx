<?php

/**
 * Measure PHPUnit coverage from a clover.xml report.
 *
 * Usage:
 *   php scripts/measure-coverage.php path/to/clover.xml
 *
 * Prints overall line + method coverage. Useful for picking a CI floor.
 */
$file = $argv[1] ?? null;
if (! $file || ! is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/measure-coverage.php path/to/clover.xml\n");
    exit(2);
}

$xml = simplexml_load_file($file);
if (! $xml) {
    fwrite(STDERR, "Failed to parse {$file}\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
if (! $metrics) {
    fwrite(STDERR, "No <project>/<metrics> in {$file}\n");
    exit(2);
}

$covered = (int) $metrics['coveredstatements'] + (int) $metrics['coveredconditionals'] + (int) $metrics['coveredmethods'];
$total = (int) $metrics['statements'] + (int) $metrics['conditionals'] + (int) $metrics['methods'];
$lineCovered = (int) $metrics['coveredstatements'];
$lineTotal = (int) $metrics['statements'];

$combinedPct = $total > 0 ? round(($covered / $total) * 100, 2) : 0.0;
$linePct = $lineTotal > 0 ? round(($lineCovered / $lineTotal) * 100, 2) : 0.0;

fwrite(STDOUT, sprintf(
    "lines:        %d/%d  (%s%%)\nmethods:      %d/%d\nconditionals: %d/%d\ncombined (S+C+M): %d/%d  (%s%%)\n",
    $lineCovered,
    $lineTotal,
    $linePct,
    (int) $metrics['coveredmethods'],
    (int) $metrics['methods'],
    (int) $metrics['coveredconditionals'],
    (int) $metrics['conditionals'],
    $covered,
    $total,
    $combinedPct,
));

// Top 10 files by uncovered statements, for floor-setting context.
$rows = [];
foreach ($xml->project->file as $f) {
    $rows[] = [
        'file' => (string) $f['name'],
        'uncovered' => (int) $f['metrics']['statements'] - (int) $f['metrics']['coveredstatements'],
    ];
}
usort($rows, fn ($a, $b) => $b['uncovered'] <=> $a['uncovered']);
fwrite(STDOUT, "\nTop uncovered:\n");
foreach (array_slice($rows, 0, 10) as $r) {
    fwrite(STDOUT, sprintf("  %4d  %s\n", $r['uncovered'], $r['file']));
}
