@props(['html' => null, 'css' => null, 'image' => null, 'size' => 48])

{{--
    A small square preview thumbnail (never circular, per this app's
    established table-thumbnail convention — see Products' picture
    column) for a raw HTML/CSS document — a Proposal Template or Proposal
    Snippet. When a real product picture is available ($image, an already
    base64-data-URI'd string per Product::getImageDataUri()) it wins over
    rendering the markup, since a real photo is more useful at this size
    than a tiny scaled-down document.

    Otherwise renders the html/css inside a sandboxed, non-interactive
    iframe at a fixed "document" size and scales the whole thing down with
    a CSS transform so it reads as a miniature page rather than a cropped
    fragment — the standard safe way to preview arbitrary HTML/CSS without
    executing it in the parent page's own DOM (App\Livewire\
    TallStackProposalTemplates/TallStackProposalSnippets).
--}}
@php
    $docSize = 800;
    $scale = $size / $docSize;
@endphp

<div
    class="rounded-md overflow-hidden bg-white dark:bg-gray-900! border border-gray-200 dark:border-gray-800! shrink-0 grid place-items-center"
    style="width: {{ $size }}px; height: {{ $size }}px;"
>
    @if ($image)
        <img src="{{ $image }}" alt="" class="w-full h-full object-cover">
    @elseif ($html || $css)
        <div style="width: {{ $size }}px; height: {{ $size }}px; overflow: hidden;">
            <iframe
                srcdoc="<style>html,body{{ '{' }}margin:0;padding:8px;background:#fff;{{ '}' }}{{ $css }}</style>{{ $html }}"
                style="width: {{ $docSize }}px; height: {{ $docSize }}px; border: 0; transform: scale({{ $scale }}); transform-origin: top left; pointer-events: none;"
                tabindex="-1"
                sandbox=""
                scrolling="no"
                aria-hidden="true"
            ></iframe>
        </div>
    @else
        <x-icon name="document" class="w-4 h-4 text-gray-300 dark:text-gray-600!" />
    @endif
</div>
