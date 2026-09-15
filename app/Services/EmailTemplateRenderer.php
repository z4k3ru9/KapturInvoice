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
     * @param  array<string, string>  $tokens  Keys already wrapped in `{{ }}`, e.g. `['{{client_name}}' => 'Acme Co']`.
     */
    public function render(string $template, array $tokens): string
    {
        return strtr($template, $tokens);
    }
}
