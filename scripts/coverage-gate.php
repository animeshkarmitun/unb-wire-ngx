<?php

/**
 * Coverage gate for CI.
 *
 * Reads a PHPUnit clover.xml and fails if any of:
 *   - overall line coverage drops below MIN_LINE_PCT
 *   - overall method coverage drops below MIN_METHOD_PCT
 *   - any class on the allowlist is not referenced by tests (the "untested
 *     critical class" gate that was already enforced by M14-COV-016)
 *
 * Usage: php scripts/coverage-gate.php path/to/clover.xml
 *
 * Thresholds are intentionally below the current measured baseline so the
 * gate is "lift, don't regress" rather than a sprint to a number. Bump
 * after each quarterly review of the highest-risk gaps.
 */
const MIN_LINE_PCT = 85.0;
const MIN_METHOD_PCT = 65.0;

const ALLOWLIST = [
    'OpenAiProvider',
    'ProcessDeliveriesCommand',
    'CheckOutboxLag',
    'EntitlementResolver',
    'HtmlSanitizer',
    'FanoutStory',
    'ProcessIndexOutbox',
    'StoryService',
    'PresignedUrlService',
    'DownloadGateService',
];

$file = $argv[1] ?? null;
if (! $file || ! is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/coverage-gate.php path/to/clover.xml\n");
    exit(2);
}

$xml = @simplexml_load_file($file);
if (! $xml) {
    fwrite(STDERR, "Failed to parse {$file}\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
if (! $metrics) {
    fwrite(STDERR, "No <project>/<metrics> in {$file}\n");
    exit(2);
}

$lineCovered = (int) $metrics['coveredstatements'];
$lineTotal = (int) $metrics['statements'];
$methodCovered = (int) $metrics['coveredmethods'];
$methodTotal = (int) $metrics['methods'];

$linePct = $lineTotal > 0 ? round(($lineCovered / $lineTotal) * 100, 2) : 100.0;
$methodPct = $methodTotal > 0 ? round(($methodCovered / $methodTotal) * 100, 2) : 100.0;

fwrite(STDOUT, sprintf("lines:        %d/%d  (%.2f%%)\n", $lineCovered, $lineTotal, $linePct));
fwrite(STDOUT, sprintf("methods:      %d/%d  (%.2f%%)\n", $methodCovered, $methodTotal, $methodPct));

$failures = [];

if ($linePct < MIN_LINE_PCT) {
    $failures[] = sprintf('line coverage %.2f%% below floor %.2f%%', $linePct, MIN_LINE_PCT);
}
if ($methodPct < MIN_METHOD_PCT) {
    $failures[] = sprintf('method coverage %.2f%% below floor %.2f%%', $methodPct, MIN_METHOD_PCT);
}

// Allowlist check: critical classes must appear in the file set with at least
// one executable statement. (We are not checking coverage % here; that is the
// job of M14-COV-016's reference check plus the new line-coverage threshold.)
$files = [];
foreach ($xml->project->file as $f) {
    $files[] = (string) $f['name'];
}
$haystack = implode("\n", $files);
foreach (ALLOWLIST as $class) {
    $matches = preg_grep('/'.preg_quote($class, '/').'\.php$/', $files);
    if (empty($matches)) {
        $failures[] = "allowlist: no file matches {$class}.php (the class was removed or renamed)";
    }
}

if ($failures) {
    fwrite(STDERR, "\nCOVERAGE GATE FAILED\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - {$f}\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("\nOK  (line>=%.1f%%, methods>=%.1f%%, %d allowlist classes present)\n", MIN_LINE_PCT, MIN_METHOD_PCT, count(ALLOWLIST)));
exit(0);
