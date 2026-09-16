@props(['crumbs' => [], 'title'])

{{--
    Shared page-header block — breadcrumb row, H1 title, an optional
    status-badge slot beside it, and a right-aligned actions slot.
    Every TALL-stack page's header was hand-copied from the Dashboard's own
    (see that file's own comment: "mirrors tallstack-dashboard.blade.php's
    own header block") — pulled out here so the next phase reuses this
    instead of copying markup again, per
    docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md's "Established
    TallStackUI patterns" section.

    $crumbs: array of ['label' => string, 'url' => string|null, 'icon' =>
    string|null] — the last crumb is rendered as plain text even if it
    carries a url, since it's the current page. Every other crumb links
    when it has a url. `icon` is optional and backward compatible: when
    present it's the same icon name already assigned to that section in
    the sidebar nav (app.blade.php's own $nav array), rendered just
    before the crumb's label; when absent (the common case today, since
    most callers haven't been given one yet) the crumb renders exactly as
    before, with no icon.

    $meta (optional slot): a compact, at-a-glance row of label/value pairs
    (e.g. Client, Date, PO number) rendered under the title/badge — added
    for record detail pages (Quotation/Job) whose own "basic info" card
    collapses via <x-card minimize>, so the essentials stay visible
    without expanding it. Every caller that doesn't pass it renders
    exactly as before.

    Every `dark:` utility below carries a trailing `!` (Tailwind v4
    important). Without it, this h1's `dark:text-gray-100` LOST the
    cascade to vendor/tallstackui/tallstackui/dist/tallstackui.css's own
    unconditional `.text-gray-900{...}` rule (compiled by some bundled
    component elsewhere in that 384KB file, with no `dark:` variant of its
    own) — that stylesheet loads after app.css
    (components/tallstack/app.blade.php's `@tallStackUiStyle`), so at equal
    specificity its always-active rule wins over app.css's own correctly
    `@media (prefers-color-scheme: dark)`-gated one regardless of the
    visitor's actual preference. Confirmed live: this title rendered as
    near-black text on the dark sidebar/header background with dark mode
    genuinely active. Same root cause, same fix, as every other
    `!important` in this codebase's dark-mode work (see
    AppServiceProvider::registerAppShellDarkModeFix()'s docblock) — the
    only difference is this is hand-authored Blade markup, not a
    `TallStackUi::customize()` block, so the fix lives here instead.
--}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500!">
            @foreach ($crumbs as $i => $crumb)
                @if ($i > 0)
                    <span>/</span>
                @endif
                @if (! empty($crumb['url']) && $i < count($crumbs) - 1)
                    <a href="{{ $crumb['url'] }}" class="inline-flex items-center gap-1 hover:underline">
                        @if (! empty($crumb['icon']))
                            <x-icon name="{{ $crumb['icon'] }}" class="h-3.5 w-3.5" />
                        @endif
                        {{ $crumb['label'] }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-1">
                        @if (! empty($crumb['icon']))
                            <x-icon name="{{ $crumb['icon'] }}" class="h-3.5 w-3.5" />
                        @endif
                        {{ $crumb['label'] }}
                    </span>
                @endif
            @endforeach
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-bold text-xl text-gray-900 dark:text-gray-100!">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @isset($meta)
            <div class="flex items-center gap-x-4 gap-y-1 flex-wrap mt-1 text-xs text-gray-500 dark:text-gray-400!">
                {{ $meta }}
            </div>
        @endisset
    </div>

    @isset($actions)
        {{-- flex-wrap (not the original plain flex row): a crowded actions
             slot — 3+ buttons, or an autosave-status indicator plus 2
             buttons — had nowhere to go at mobile width, other than
             overflowing or squeezing each button's own label text (see
             App\Providers\AppServiceProvider's button() customize() call
             for the matching `shrink-0 whitespace-nowrap` fix on the
             button side of this same bug). justify-end keeps the row
             right-aligned on desktop and right-aligned per wrapped line
             on mobile, matching this slot's original intent. --}}
        <div class="flex flex-wrap items-center justify-end gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
