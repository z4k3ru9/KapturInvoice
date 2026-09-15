<?php

namespace App\Support\Html;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Server-side defense-in-depth for the `<x-editor>` rich-text fields
 * (invoice/quotation/vendor-document terms/footer/notes, client/vendor
 * notes, email template bodies — see CLAUDE.md's Editor conversion notes).
 *
 * TallStackUI's `<x-editor>` already sanitizes in the browser (both on
 * paste and on every sync back to the bound property), but per its own
 * docs ("Sanitize the content on the server before persisting it and
 * before rendering it back. ... Nothing the browser does can be trusted
 * by the time it reaches a database.") that client-side pass is not the
 * defense. This mirrors the editor's own default `sanitization` config
 * (`vendor/tallstackui/tallstackui/src/config.php`) rather than
 * inventing a separate whitelist, so content that round-trips through
 * the editor is never stripped by this second pass, and strips exactly
 * what a hand-crafted payload bypassing the browser entirely could still
 * contain: disallowed tags, disallowed attributes (in particular any
 * `on*` event handler), and `javascript:`/`vbscript:`/`data:` URL
 * schemes on `href`/`src` (except `data:image/` on an `<img src>`).
 *
 * No new Composer dependency was added for this — `ext-dom` is already
 * part of PHP's standard library and already available in this app
 * (confirmed via `php -m`), and this project's only other genuinely raw
 * HTML field (`Proposal`/`ProposalTemplate::html`) is deliberately
 * unsanitized admin-authored source, not a precedent to follow for a
 * WYSIWYG field that also feeds outbound email and printed PDFs.
 */
class RichTextSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'code', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5',
        'ul', 'ol', 'li',
        'blockquote', 'hr',
        'a', 'img', 'span', 'div',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'span' => ['style'],
        'div' => ['style'],
        'p' => ['style'],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'h5' => ['style'],
        'li' => ['style'],
    ];

    private const BLOCKED_URL_SCHEMES = ['javascript:', 'vbscript:', 'data:'];

    /**
     * Disallowed tags whose *content* is also unsafe to keep — removed
     * whole rather than unwrapped (an unwrapped `<script>` would dump its
     * raw JS text straight into the document; `<script>`/`<style>` content
     * isn't parsed as child elements in the first place, so unwrapping
     * would insert it verbatim as if it were an editor's own text).
     */
    private const REMOVE_ENTIRELY = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'svg', 'math', 'template'];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="tsui-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $root = $document->getElementById('tsui-root');

        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $inner = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $inner .= $document->saveHTML($child);
        }

        return trim($inner);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, self::REMOVE_ENTIRELY, true)) {
                        $parent->removeChild($node);

                        continue;
                    }

                    // Unwrap: keep any text/allowed content inside, drop the tag itself.
                    $this->cleanChildren($node);

                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }

                    $parent->removeChild($node);

                    continue;
                }

                $this->cleanAttributes($node, $tag);
                $this->cleanChildren($node);
            }
        }
    }

    private function cleanAttributes(DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && $this->hasBlockedScheme($attribute->nodeValue, $tag, $name)) {
                $node->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    private function hasBlockedScheme(?string $value, string $tag, string $attribute): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', (string) $value) ?? '');

        foreach (self::BLOCKED_URL_SCHEMES as $scheme) {
            if (! str_starts_with($normalized, $scheme)) {
                continue;
            }

            // `data:image/` stays allowed on an <img src>, matching the editor's own rule.
            if ($scheme === 'data:' && $tag === 'img' && $attribute === 'src' && str_starts_with($normalized, 'data:image/')) {
                return false;
            }

            return true;
        }

        return false;
    }
}
