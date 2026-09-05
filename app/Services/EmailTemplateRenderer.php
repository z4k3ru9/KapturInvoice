<?php

namespace App\Services;

/**
 * Substitutes `{{token}}` placeholders in the plain-text subject/body
 * templates stored on `CompanySetting` (see
 * docs/filament-admin-layout-design.md §3.3) — deliberately simple
 * `strtr()` substitution rather than a templating engine, since the
 * settings form treats these as plain text fields, not Blade/Markdown.
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
