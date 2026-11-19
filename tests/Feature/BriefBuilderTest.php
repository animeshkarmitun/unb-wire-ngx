<?php

namespace Tests\Feature;

use App\Support\BriefBuilder;
use Tests\TestCase;

/**
 * COS-78: briefs stand on their own â€” no mid-word cuts, no stray newlines.
 */
class BriefBuilderTest extends TestCase
{
    public function test_normalises_newlines_and_double_spaces(): void
    {
        $out = BriefBuilder::fromPlainText("First line.\r\nSecond  line");

        $this->assertSame('First line. Second line', $out);
    }

    public function test_cut_prefers_sentence_boundary(): void
    {
        $text = str_repeat('Sentence one is here. ', 8).'Ends mid sentence without punctuation trailing';
        $out = BriefBuilder::fromPlainText($text, 100);

        $this->assertStringEndsWith('.', $out);
        $this->assertStringNotContainsString("\n", $out);
        $this->assertLessThanOrEqual(100, mb_strlen($out));
    }

    public function test_cut_never_breaks_mid_word(): void
    {
        $text = 'One single verylongunbreakabletoken here and then more words follow immediately afterwards all the way';
        $out = BriefBuilder::fromPlainText($text, 40);

        $this->assertFalse(str_ends_with($out, 'verylongunbreakableto'), 'must not cut inside a word');
        $this->assertLessThanOrEqual(41, mb_strlen($out));
    }

    public function test_short_text_untouched(): void
    {
        $this->assertSame('Short brief.', BriefBuilder::fromPlainText('Short brief.'));
    }
}
