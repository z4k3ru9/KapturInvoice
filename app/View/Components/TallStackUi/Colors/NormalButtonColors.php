<?php

namespace App\View\Components\TallStackUi\Colors;

use Illuminate\View\Component;

/**
 * Adds a `brand` color option to `<x-button color="brand">`
 * (`App\Providers\AppServiceProvider::registerTallStackUiCustomizations()`
 * documents the semantic role this maps to — "Primary", the single
 * main-action button on a page/modal).
 *
 * Published per TallStackUI's own "Color Personalization" mechanism
 * (`php artisan tallstackui:setup-color`, vendor/tallstackui/tallstackui/
 * CLAUDE.md "Color Personalization" section; the real, current v4.1.0
 * source read directly — not guessed — at
 * vendor/tallstackui/tallstackui/src/Support/Colors/Components/
 * NormalButtonColors.php and vendor/tallstackui/tallstackui/src/
 * Components/Button/Normal/Component.php's `#[ColorsThroughOf(...)]`
 * attribute): this class's class-basename ("NormalButtonColors", derived
 * from that attribute) and namespace (`config('ts-ui.color_classes_
 * namespace')`, defaulting to `App\View\Components\TallStackUi\Colors`
 * — never published/overridden in this app's own config, so the
 * package's stock default applies) are exactly what
 * `TallStackUi\Support\Colors\Concerns\SetupColors::setup()` looks for
 * at render time via `__ts_class_collection()`. Every array key left
 * `null` here falls through to the package's own built-in default for
 * that color/style (confirmed from the vendor source's own `SetupColors`
 * merge logic — `data_get($override, $getter) ?? data_get($default,
 * $getter)`), so this file only ever ADDS the "brand" key; it never
 * touches Karunia/Axen's actual `black`/`gray`/`red`/`green`/`amber`
 * button rendering.
 *
 * "brand" is NOT one of TallStackUI's built-in Tailwind palette names
 * (see the full `BALLOONS`-style list in the vendor source — primary,
 * secondary, slate, gray, ... taupe; no "brand" among them), so it
 * can't be reached by customizing an existing color's classes — a
 * genuinely new color key is required, which is exactly what this
 * mechanism is for. Every class below reads the CURRENT TENANT's own
 * `--ts-primary` CSS custom property (set inline per company on <html>
 * in resources/views/components/tallstack/app.blade.php — Karunia
 * Abadi's red #E63934, Axen Technology's blue #5065A8, etc.) via
 * Tailwind v4 arbitrary-value `[color:...]` syntax, the same
 * `color-mix(in srgb, var(--ts-primary) X%, ...)` technique the card
 * header brand tint already uses in this same provider — chosen over
 * hand-picking a literal hover/focus shade because it needs no
 * knowledge of what the brand hex actually is (works for any company's
 * primary_color, not just the two seeded ones) and, mixed toward
 * black/white (not toward a fixed gray), it darkens/lightens whatever
 * hue the tenant picked instead of muddying it toward gray.
 *
 * Only `solid` (this app's only button style in practice — every real
 * `<x-button>` call site omits `outline`/`light`/`flat`) is genuinely
 * exercised and screenshot-verified; `outline`/`light`/`flat` below are
 * filled in for completeness with the package's own contract (every
 * style key must resolve to *something*, even if unused today) rather
 * than left to silently 404 into a blank class string if a future page
 * ever opts into one of those styles for a `color="brand"` button.
 */
class NormalButtonColors
{
    /**
     * Background colors.
     */
    public function backgroundColors(Component $component): array
    {
        return [
            'solid' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                // Mirrors the vendor default `primary` entry's own shape
                // (text-*-50 on a *-500 bg, *-600 on hover/focus, a
                // darker *-700 resting/*-600 hover in dark mode) with
                // every literal Tailwind shade swapped for a
                // color-mix() step against --ts-primary.
                'brand' => 'text-white ring-[color:var(--ts-primary)] bg-[color:var(--ts-primary)] focus:bg-[color:color-mix(in_srgb,var(--ts-primary)_85%,black)] hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_85%,black)] border-transparent focus:ring-offset-2 dark:focus:ring-offset-dark-900 dark:focus:ring-[color:var(--ts-primary)] dark:bg-[color:color-mix(in_srgb,var(--ts-primary)_80%,black)] dark:hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_65%,black)] dark:hover:ring-[color:var(--ts-primary)]',
            ],
            'outline' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-[color:var(--ts-primary)] border-[color:var(--ts-primary)] hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] focus:ring-offset-0 focus:text-[color:var(--ts-primary)] focus:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] focus:ring-[color:var(--ts-primary)] hover:text-[color:var(--ts-primary)] dark:hover:text-[color:var(--ts-primary)] dark:hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] dark:focus:border-transparent dark:focus:text-[color:var(--ts-primary)] dark:focus:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] dark:focus:ring-[color:var(--ts-primary)]',
            ],
            'light' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-[color:color-mix(in_srgb,var(--ts-primary)_80%,black)] ring-[color:color-mix(in_srgb,var(--ts-primary)_60%,white)] bg-[color:color-mix(in_srgb,var(--ts-primary)_35%,white)] hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_50%,white)] border-transparent focus:ring-offset-2 dark:focus:text-[color:var(--ts-primary)] dark:focus:ring-offset-dark-900 dark:focus:ring-[color:var(--ts-primary)] dark:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] dark:hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_30%,transparent)] dark:text-[color:var(--ts-primary)] dark:hover:ring-[color:var(--ts-primary)]',
            ],
            'flat' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'focus:ring-offset-background-white text-[color:var(--ts-primary)] hover:text-[color:color-mix(in_srgb,var(--ts-primary)_85%,black)] hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] dark:hover:text-[color:var(--ts-primary)] dark:hover:bg-[color:color-mix(in_srgb,var(--ts-primary)_10%,transparent)] focus:ring-offset-0 focus:text-[color:color-mix(in_srgb,var(--ts-primary)_85%,black)] focus:bg-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)] focus:ring-[color:var(--ts-primary)] dark:focus:text-[color:var(--ts-primary)] dark:focus:bg-[color:color-mix(in_srgb,var(--ts-primary)_10%,transparent)] dark:focus:ring-[color:var(--ts-primary)]',
            ],
        ];
    }

    /**
     * Icon colors.
     */
    public function iconColors(Component $component): array
    {
        return [
            'solid' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-white',
            ],
            'outline' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-[color:var(--ts-primary)]',
            ],
            'light' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-[color:color-mix(in_srgb,var(--ts-primary)_80%,black)] dark:text-[color:var(--ts-primary)]',
            ],
            'flat' => [
                'black' => null,
                'primary' => null,
                'secondary' => null,
                'slate' => null,
                'gray' => null,
                'zinc' => null,
                'neutral' => null,
                'stone' => null,
                'red' => null,
                'orange' => null,
                'amber' => null,
                'yellow' => null,
                'lime' => null,
                'green' => null,
                'emerald' => null,
                'teal' => null,
                'cyan' => null,
                'sky' => null,
                'blue' => null,
                'indigo' => null,
                'violet' => null,
                'purple' => null,
                'fuchsia' => null,
                'pink' => null,
                'rose' => null,
                'mauve' => null,
                'olive' => null,
                'mist' => null,
                'taupe' => null,
                'brand' => 'text-[color:var(--ts-primary)]',
            ],
        ];
    }
}
