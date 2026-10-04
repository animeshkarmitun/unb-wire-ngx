<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_tag(): void
    {
        $out = HtmlSanitizer::clean('<p>hi</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    public function test_strips_iframe(): void
    {
        $out = HtmlSanitizer::clean('<iframe src="evil"></iframe><p>safe</p>');
        $this->assertStringNotContainsString('<iframe', $out);
    }

    public function test_strips_onclick_and_onload(): void
    {
        $out = HtmlSanitizer::clean('<a href="/x" onclick="x">A</a><img src="x" onerror="bad">');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror', $out);
    }

    public function test_strips_javascript_href(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_strips_data_text_html_href(): void
    {
        $out = HtmlSanitizer::clean('<a href="data:text/html,<script>1</script>">x</a>');
        $this->assertStringNotContainsString('data:text/html', $out);
    }

    public function test_strips_entity_encoded_javascript_href(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript&#58;alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    public function test_strips_vbscript_href(): void
    {
        $out = HtmlSanitizer::clean('<a href="vbscript:msgbox(1)">x</a>');
        $this->assertStringNotContainsString('vbscript', $out);
    }

    public function test_strips_img_onerror(): void
    {
        $out = HtmlSanitizer::clean('<img src="x" onerror="bad">');
        $this->assertStringNotContainsString('onerror', $out);
    }

    public function test_strips_svg_onload(): void
    {
        $out = HtmlSanitizer::clean('<svg onload="bad()"></svg>');
        $this->assertStringNotContainsString('onload', $out);
    }

    public function test_data_href_text_html_is_stripped(): void
    {
        $out = HtmlSanitizer::clean('<a href="data:text/html,<script>1</script>">x</a>');
        $this->assertStringNotContainsString('data:text/html', $out);
        $this->assertStringNotContainsString('alert', $out);
    }

    public function test_unwraps_unknown_tags_keeping_text(): void
    {
        $out = HtmlSanitizer::clean('<section><p>safe</p></section>');
        $this->assertStringContainsString('safe', $out);
        $this->assertStringNotContainsString('<section', $out);
    }

    public function test_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
