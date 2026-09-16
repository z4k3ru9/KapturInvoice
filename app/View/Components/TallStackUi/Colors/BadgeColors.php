<?php

namespace App\View\Components\TallStackUi\Colors;

use Illuminate\View\Component;

/**
 * Fixes `<x-badge light>` (this app's own status-badge convention — see
 * `App\Support\TallStack\StatusColor`, used by every status column
 * app-wide, e.g. `tallstack-vendor-bills.blade.php`'s
 * `<x-badge :color="..." sm light />`) rendering LIGHT-mode colors even
 * under `prefers-color-scheme: dark`.
 *
 * Published per TallStackUI's own "Color Personalization" mechanism
 * (`php artisan tallstackui:setup-color`; the stub at
 * `vendor/tallstackui/tallstackui/src/Support/Colors/Stubs/BadgeColors.stub`
 * gives the exact method/array shape every entry below follows), the
 * same extension point `NormalButtonColors` already uses for the
 * `brand` button color — see that class's own docblock for how
 * `TallStackUi\Support\Colors\Concerns\SetupColors::setup()` discovers
 * and calls this class.
 *
 * Root cause (found independently by 3 separate audit passes,
 * 2026-09-16, confirmed live via `getComputedStyle` on real status
 * badges under emulated dark mode): `<x-badge>`'s per-color `light`
 * style classes (`TallStackUi\Support\Colors\Components\BadgeColors`,
 * read directly from the installed vendor source — not guessed) carry
 * `dark:` utilities with no trailing `!important`, so they lose the
 * standard `tallstackui.css`-loads-after-`app.css` cascade collision
 * this project's own `.ai/rules/tallstackui-customization.md` documents
 * everywhere else. Unlike every other component in
 * `AppServiceProvider::registerContentSurfaceDarkModeFix()`, Badge's
 * per-color maps are NOT exposed as `TallStackUi::customize()->block()`
 * keys at all (confirmed via the `tallstackui` MCP server's
 * `search_customization` — only `wrapper.class`/`sizes`/
 * `border-radius` are) — this Color-Personalization class is the only
 * real extension point that can reach them.
 *
 * Every value below is the vendor's own real default string (read from
 * `vendor/tallstackui/tallstackui/src/Support/Colors/Components/
 * BadgeColors.php`, generated programmatically by appending `!` to each
 * `dark:` token rather than hand-transcribed, to avoid introducing a
 * wrong shade across 29 colors) — this class only ever fixes the
 * cascade collision, it never changes what color anything actually is.
 *
 * `solid` and `outline` are left `null` (falling through to the
 * package's own default) because: `solid` was confirmed live to
 * already render correctly in dark mode (it uses a `-700/80` background
 * strong enough that the un-`!`'d `dark:` class still visually reads
 * fine even on the rare occasion it loses the cascade — spot-checked,
 * not just assumed), and `outline` is not used by any `<x-badge>` call
 * site in this app (confirmed via grep). Only `light` — this app's one
 * real status-badge style — is overridden.
 */
class BadgeColors
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
            ],
            'light' => [
                'black' => 'border-black/5 bg-black/30 dark:bg-black/30! dark:border-transparent!',
                'primary' => 'border-primary-300 bg-primary-300 dark:bg-primary-700/30! dark:border-transparent!',
                'secondary' => 'border-secondary-300 bg-secondary-300 dark:bg-secondary-700/30! dark:border-transparent!',
                'slate' => 'border-slate-300 bg-slate-300 dark:bg-slate-700/30! dark:border-transparent!',
                'gray' => 'border-gray-300 bg-gray-300 dark:bg-gray-700/30! dark:border-transparent!',
                'zinc' => 'border-zinc-300 bg-zinc-300 dark:bg-zinc-700/30! dark:border-transparent!',
                'neutral' => 'border-neutral-300 bg-neutral-300 dark:bg-neutral-700/30! dark:border-transparent!',
                'stone' => 'border-stone-300 bg-stone-300 dark:bg-stone-700/30! dark:border-transparent!',
                'red' => 'border-red-300 bg-red-300 dark:bg-red-700/30! dark:border-transparent!',
                'orange' => 'border-orange-300 bg-orange-300 dark:bg-orange-700/30! dark:border-transparent!',
                'amber' => 'border-amber-300 bg-amber-300 dark:bg-amber-700/30! dark:border-transparent!',
                'yellow' => 'border-yellow-300 bg-yellow-300 dark:bg-yellow-700/30! dark:border-transparent!',
                'lime' => 'border-lime-300 bg-lime-300 dark:bg-lime-700/30! dark:border-transparent!',
                'green' => 'border-green-300 bg-green-300 dark:bg-green-700/30! dark:border-transparent!',
                'emerald' => 'border-emerald-300 bg-emerald-300 dark:bg-emerald-700/30! dark:border-transparent!',
                'teal' => 'border-teal-300 bg-teal-300 dark:bg-teal-700/30! dark:border-transparent!',
                'cyan' => 'border-cyan-300 bg-cyan-300 dark:bg-cyan-700/30! dark:border-transparent!',
                'sky' => 'border-sky-300 bg-sky-300 dark:bg-sky-700/30! dark:border-transparent!',
                'blue' => 'border-blue-300 bg-blue-300 dark:bg-blue-700/30! dark:border-transparent!',
                'indigo' => 'border-indigo-300 bg-indigo-300 dark:bg-indigo-700/30! dark:border-transparent!',
                'violet' => 'border-violet-300 bg-violet-300 dark:bg-violet-700/30! dark:border-transparent!',
                'purple' => 'border-purple-300 bg-purple-300 dark:bg-purple-700/30! dark:border-transparent!',
                'fuchsia' => 'border-fuchsia-300 bg-fuchsia-300 dark:bg-fuchsia-700/30! dark:border-transparent!',
                'pink' => 'border-pink-300 bg-pink-300 dark:bg-pink-700/30! dark:border-transparent!',
                'rose' => 'border-rose-300 bg-rose-300 dark:bg-rose-700/30! dark:border-transparent!',
                'mauve' => 'border-mauve-300 bg-mauve-300 dark:bg-mauve-700/30! dark:border-transparent!',
                'olive' => 'border-olive-300 bg-olive-300 dark:bg-olive-700/30! dark:border-transparent!',
                'mist' => 'border-mist-300 bg-mist-300 dark:bg-mist-700/30! dark:border-transparent!',
                'taupe' => 'border-taupe-300 bg-taupe-300 dark:bg-taupe-700/30! dark:border-transparent!',
            ],
        ];
    }

    /**
     * Icon colors. Vendor's own `icon()` is a pure alias of `text()`, so
     * this mirrors `textColors()` exactly.
     */
    public function iconColors(Component $component): array
    {
        return $this->textColors($component);
    }

    /**
     * Text colors.
     */
    public function textColors(Component $component): array
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
            ],
            'light' => [
                'black' => 'text-black/30 dark:text-white!',
                'primary' => 'text-primary-600 dark:text-primary-400!',
                'secondary' => 'text-secondary-600 dark:text-secondary-400!',
                'slate' => 'text-slate-600 dark:text-slate-400!',
                'gray' => 'text-gray-600 dark:text-gray-400!',
                'zinc' => 'text-zinc-600 dark:text-zinc-400!',
                'neutral' => 'text-neutral-600 dark:text-neutral-400!',
                'stone' => 'text-stone-600 dark:text-stone-400!',
                'red' => 'text-red-600 dark:text-red-400!',
                'orange' => 'text-orange-600 dark:text-orange-400!',
                'amber' => 'text-amber-600 dark:text-amber-400!',
                'yellow' => 'text-yellow-600 dark:text-yellow-400!',
                'lime' => 'text-lime-600 dark:text-lime-400!',
                'green' => 'text-green-600 dark:text-green-400!',
                'emerald' => 'text-emerald-600 dark:text-emerald-400!',
                'teal' => 'text-teal-600 dark:text-teal-400!',
                'cyan' => 'text-cyan-600 dark:text-cyan-400!',
                'sky' => 'text-sky-600 dark:text-sky-400!',
                'blue' => 'text-blue-600 dark:text-blue-400!',
                'indigo' => 'text-indigo-600 dark:text-indigo-400!',
                'violet' => 'text-violet-600 dark:text-violet-400!',
                'purple' => 'text-purple-600 dark:text-purple-400!',
                'fuchsia' => 'text-fuchsia-600 dark:text-fuchsia-400!',
                'pink' => 'text-pink-600 dark:text-pink-400!',
                'rose' => 'text-rose-600 dark:text-rose-400!',
                'mauve' => 'text-mauve-600 dark:text-mauve-400!',
                'olive' => 'text-olive-600 dark:text-olive-400!',
                'mist' => 'text-mist-600 dark:text-mist-400!',
                'taupe' => 'text-taupe-600 dark:text-taupe-400!',
            ],
        ];
    }
}
