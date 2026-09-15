<?php

namespace Tests\Unit;

use App\Support\Html\RichTextSanitizer;
use Tests\TestCase;

/**
 * Server-side defense-in-depth for the `<x-editor>` rich-text fields — see
 * App\Support\Html\RichTextSanitizer's docblock. Mirrors the editor
 * package's own default sanitization whitelist
 * (vendor/tallstackui/tallstackui/src/config.php) rather than inventing a
 * separate one, and adds the same scheme checks its docs describe
 * (`javascript:`/`vbscript:`/`data:` blocked on href/src, except
 * `data:image/` on an `<img src>`).
 */
class RichTextSanitizerTest extends TestCase
{
    private RichTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new RichTextSanitizer;
    }

    public function test_it_keeps_allowed_formatting_tags(): void
    {
        $html = '<p>Payment is due <strong>within 30 days</strong> of the <em>invoice date</em>.</p>'
            .'<ul><li>First item</li><li>Second item</li></ul>';

        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function test_it_removes_a_script_tag_entirely_including_its_content(): void
    {
        $result = $this->sanitizer->sanitize('<p>Terms</p><script>alert("xss")</script>');

        $this->assertSame('<p>Terms</p>', $result);
    }

    public function test_it_strips_an_event_handler_attribute_but_keeps_the_element(): void
    {
        $result = $this->sanitizer->sanitize('<p onmouseover="alert(1)">Hover me</p>');

        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringContainsString('Hover me', $result);
    }

    public function test_it_strips_a_javascript_scheme_href(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">Click</a>');

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('Click', $result);
    }

    public function test_it_keeps_a_data_image_src_on_img_but_strips_a_data_scheme_href_on_a_link(): void
    {
        $imgResult = $this->sanitizer->sanitize('<img src="data:image/png;base64,abc123" alt="Logo">');
        $this->assertStringContainsString('data:image/png;base64,abc123', $imgResult);

        $linkResult = $this->sanitizer->sanitize('<a href="data:text/html,<script>alert(1)</script>">Click</a>');
        $this->assertStringNotContainsString('data:text/html', $linkResult);
    }

    public function test_it_unwraps_a_disallowed_but_harmless_tag_and_keeps_its_text(): void
    {
        $result = $this->sanitizer->sanitize('<section>Some prose</section>');

        $this->assertStringNotContainsString('<section', $result);
        $this->assertStringContainsString('Some prose', $result);
    }

    public function test_it_removes_an_iframe_entirely(): void
    {
        $result = $this->sanitizer->sanitize('<iframe src="https://evil.example">not allowed</iframe>');

        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringNotContainsString('evil.example', $result);
    }

    public function test_it_leaves_null_and_blank_values_untouched(): void
    {
        $this->assertNull($this->sanitizer->sanitize(null));
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function test_it_leaves_plain_text_with_no_tags_untouched(): void
    {
        $this->assertSame('Net 30', $this->sanitizer->sanitize('Net 30'));
    }
}
