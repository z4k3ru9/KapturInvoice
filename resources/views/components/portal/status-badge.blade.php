@props(['label', 'color' => 'gray'])

{{--
    Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) — a
    real WCAG 2.2 AA gap found via the axe-core browser scan: Filament's
    own `<x-badge>` component (used elsewhere for the same status labels)
    renders a bold `bg-{color}-500`/`text-{color}-50` combination that
    measures well under the required 4.5:1 contrast ratio for small bold
    text (axe: color-contrast, 3.99 measured on the public portal's
    "Issued" badge) — and, worked out from Tailwind's real palette values,
    every one of this app's status colors fails the same way at that
    weight, not just "primary"; picking a different named color would not
    have fixed it. This component renders the identical status labels/
    colors with a light-background/dark-text pairing that clears 4.5:1 for
    every color below, used only on the public (unauthenticated) portal
    pages — the admin panel's own status badges are untouched.

    Classes are written out per branch (not interpolated, e.g. never
    `bg-{{ $color }}-100`) so Tailwind's content scanner can actually find
    them — an interpolated class name is invisible to it and gets purged
    from the production build.

    A second, real bug found in dark mode specifically (axe: color-
    contrast, first 1.29:1 on "Partially paid", then 1.2:1 on "Draft"
    once the first was worked around): a plain `dark:bg-{color}-*` /
    `dark:text-{color}-*` never reliably overrides its light counterpart
    in this build — confirmed correctly compiled *and correctly wrapped*
    in `@media (prefers-color-scheme: dark)`, and confirmed
    `prefers-color-scheme: dark` genuinely active via
    `window.matchMedia()` at the time — yet `getComputedStyle()` still
    reports the light value for ONE of the two colored properties on a
    given badge (which property loses varied: background lost for the
    amber/indigo badges, then text lost for the gray one once background
    was forced). Same specificity, same media condition, same element —
    a real, unexplained Tailwind v4/lightningcss cascade quirk somewhere
    else in this build's CSS, not a mistake in these classes. Every
    dark: utility here is `!important` (Tailwind v4's trailing `!`) as
    the pragmatic fix, since it reliably wins regardless of whatever
    that other cascade issue is. Worth a proper root-cause pass with
    real time — a wider audit of every OTHER `dark:` usage already in
    this app is probably warranted, since nothing about the underlying
    cascade issue is specific to a portal-only component.
--}}
@php
    $classes = match ($color) {
        'success' => 'bg-green-100 text-green-800 border-green-300 dark:bg-green-950! dark:text-green-300! dark:border-green-800!',
        'danger' => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950! dark:text-red-300! dark:border-red-800!',
        'warning' => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950! dark:text-amber-300! dark:border-amber-800!',
        'info' => 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950! dark:text-blue-300! dark:border-blue-800!',
        'primary' => 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950! dark:text-indigo-300! dark:border-indigo-800!',
        default => 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-900! dark:text-gray-300! dark:border-gray-700!',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-bold {$classes}"]) }}>
    {{ $label }}
</span>
