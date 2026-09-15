<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? ($company->name ?? config('app.name')) }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Pushed by the portfolio homepage view only (Google Fonts for its
             "Kinetic Obsidian" design system) — empty on the portal page. --}}
        @stack('head')

        @livewireStyles
        @tallStackUiStyle
    </head>
    {{--
        dark:bg-*/dark:text-* here use `!` important — see
        resources/views/components/portal/status-badge.blade.php's
        docblock for the established root cause: a plain dark: utility
        from this app's own compiled CSS doesn't reliably outrank
        TallStackUI's later-loaded stylesheet (@tallStackUiStyle) at
        equal specificity, so the light-mode value silently wins even
        while prefers-color-scheme: dark is genuinely active. Without
        this, every page using this layout (the portal, the public
        homepage) renders unreadable dark-text-on-dark-background in
        system dark mode.
    --}}
    <body class="bg-white text-gray-900 antialiased dark:bg-gray-900! dark:text-gray-100!">
        {{ $slot }}

        @livewireScripts
        @tallStackUiScript
    </body>
</html>
