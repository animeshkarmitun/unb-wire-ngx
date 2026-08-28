<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_and_iframe(): void
    {
        $in = '<p>Hello</p><script>alert(1)</script><iframe src="x"></iframe>';
        $out = HtmlSanitizer::clean($in);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringContainsString('<p>Hello</p>', $out);
    }

    public function test_strips_event_handlers(): void
    {
        $out = HtmlSanitizer::clean('<p onclick="alert(1)" onload="x">hi</p>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onload', $out);
    }

    public function test_strips_javascript_href(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">click</a>');
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('click', $out);
    }

    public function test_allows_safe_tags(): void
    {
        $in = '<p><strong>bold</strong> <em>em</em> <a href="https://example.test">link</a></p><ul><li>item</li></ul><blockquote>q</blockquote>';
        $out = HtmlSanitizer::clean($in);
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringContainsString('<em>em</em>', $out);
        $this->assertStringContainsString('href="https://example.test"', $out);
    }

    public function test_text_extraction(): void
    {
        $this->assertEquals('Hello world', HtmlSanitizer::text('<p>Hello <b>world</b></p>'));
        $this->assertEquals('', HtmlSanitizer::text(null));
        $this->assertEquals('', HtmlSanitizer::text(''));
    }

    public function test_unwraps_disallowed_but_keeps_children(): void
    {
        $out = HtmlSanitizer::clean('<section><p>kept</p></section>');
        $this->assertStringContainsString('<p>kept</p>', $out);
        $this->assertStringNotContainsString('<section', $out);
    }

    public function test_blocks_data_text_html_in_src(): void
    {
        $out = HtmlSanitizer::clean('<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>');
        $this->assertStringNotContainsString('data:text/html', $out);
    }
}
