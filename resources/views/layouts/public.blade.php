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
    <body class="bg-white text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        {{ $slot }}

        @livewireScripts
        @tallStackUiScript
    </body>
</html>
