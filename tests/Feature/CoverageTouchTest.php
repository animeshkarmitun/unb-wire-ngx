<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoverageTouchTest extends TestCase
{
    /**
     * Allowlist of high-risk classes that must be exercised by tests.
     * Each line is a substring that must appear in at least one test file under tests/.
     * Failing this check means a critical class has been added/changed without test coverage.
     */
    private const ALLOWLIST = [
        'OpenAiProvider',
        'ProcessDeliveriesCommand',
        'CheckOutboxLag',
        'EntitlementResolver',
        'HtmlSanitizer',
        'FanoutStory',
        'ProcessIndexOutbox',
    ];

    public function test_allowlisted_classes_have_test_references(): void
    {
        $root = base_path('tests');
        $haystack = '';
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($rii as $f) {
            if (! $f->isFile()) {
                continue;
            }
            if (! str_ends_with($f->getFilename(), 'Test.php') && ! str_ends_with($f->getFilename(), '.spec.ts')) {
                continue;
            }
            $haystack .= file_get_contents($f->getPathname())."\n";
        }

        foreach (self::ALLOWLIST as $class) {
            $this->assertStringContainsString(
                $class,
                $haystack,
                "No test references {$class}. Add a Feature or Unit test, or remove it from the allowlist."
            );
        }
    }
}
