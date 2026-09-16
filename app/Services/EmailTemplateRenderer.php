<?php

namespace App\Services;

/**
 * Substitutes `{{token}}` placeholders in the subject/body templates
 * stored on `CompanySetting` (see docs/filament-admin-layout-design.md
 * §3.3) — deliberately simple `strtr()` substitution rather than a
 * templating engine. The subject stays plain text; the body is
 * server-sanitized HTML typed into `<x-editor>`
 * (`App\Livewire\TallStackSettingsEmail`, `App\Support\Html\
 * RichTextSanitizer`) or the hardcoded plain-text fallback in
 * `App\Services\BillingMailer::templateFor()` — see
 * `resources/views/emails/plain.blade.php` for how the two are told
 * apart at render time.
 */
class EmailTemplateRenderer
{
    /**
     * Plain substitution — safe for a mail *subject* (never rendered as
     * HTML, so escaping would just show literal `&amp;` etc. to the
     * recipient) and for a body template that `resources/views/emails/
     * plain.blade.php` is going to run through `e()` as a whole anyway
     * (the plain-text-fallback branch). Do not use this for an HTML body —
     * see `renderHtml()`.
     *
     * @param  array<string, string>  $tokens  Keys already wrapped in `{{ }}`, e.g. `['{{client_name}}' => 'Acme Co']`.
     */
    public function render(string $template, array $tokens): string
    {
        return strtr($template, $tokens);
    }

    /**
     * Same substitution, but HTML-escapes every token's value first. The
     * sanitized `<x-editor>` body template itself is trusted (server-
     * sanitized via `App\Support\Html\RichTextSanitizer` on save), but its
     * token *values* — a client/contact name, for instance — are ordinary
     * free-text data, not sanitized HTML. `resources/views/emails/
     * plain.blade.php` renders an HTML-looking body with `{!! !!}`
     * (unescaped), so substituting a raw token value into it would let
     * something like a client named `<img src=x onerror=alert(1)>` inject
     * markup into an otherwise-sanitized outbound email. Use this for
     * every body render; keep `render()` for the subject.
     *
     * @param  array<string, string>  $tokens
     */
    public function renderHtml(string $template, array $tokens): string
    {
        return strtr($template, array_map(static fn (string $value): string => e($value), $tokens));
    }
}
