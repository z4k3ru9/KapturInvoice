@props(['crumbs' => [], 'title'])

{{--
    Shared page-header block — breadcrumb row, H1 title, an optional
    status-badge slot beside it, and a right-aligned actions slot.
    Every TALL-stack page's header was hand-copied from the Dashboard's own
    (see that file's own comment: "mirrors tallstack-dashboard.blade.php's
    own header block") — pulled out here so the next phase reuses this
    instead of copying markup again, per
    docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's "Established
    TallStackUI patterns" section.

    $crumbs: array of ['label' => string, 'url' => string|null] — the last
    crumb is rendered as plain text even if it carries a url, since it's
    the current page. Every other crumb links when it has a url.

    $meta (optional slot): a compact, at-a-glance row of label/value pairs
    (e.g. Client, Date, PO number) rendered under the title/badge — added
    for record detail pages (Quotation/Job) whose own "basic info" card
    collapses via <x-card minimize>, so the essentials stay visible
    without expanding it. Every caller that doesn't pass it renders
    exactly as before.
--}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <div class="flex items-center gap-1.5 text-xs text-gray-400">
            @foreach ($crumbs as $i => $crumb)
                @if ($i > 0)
                    <span>/</span>
                @endif
                @if (! empty($crumb['url']) && $i < count($crumbs) - 1)
                    <a href="{{ $crumb['url'] }}" class="hover:underline">{{ $crumb['label'] }}</a>
                @else
                    <span>{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-bold text-xl text-gray-900 dark:text-gray-100">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @isset($meta)
            <div class="flex items-center gap-x-4 gap-y-1 flex-wrap mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $meta }}
            </div>
        @endisset
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
