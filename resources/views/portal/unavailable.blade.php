{{--
    The calm, branded "this link is no longer available" page — Phase 11
    (TallStackUI client portal restyle), matching the Stitch mockup
    "Client Portal - Access Expired & Security Verification".

    Rendered directly (not via a Livewire component) by
    App\Livewire\Portal\ViewInvoice::mount()/ClientPortalHome::mount()
    whenever their existing `abort_unless(...)` authorization check fails
    — an unknown, cross-company, revoked, or expired link. The check
    itself is unchanged; only what gets rendered for that same 404 is
    different now. Per docs/rebuild/DESIGN.md §11, this page must never
    reveal whether a client, contact, or document actually exists, so
    every reason above renders identically — no status code, session
    token, or "reason" string anywhere on the page.

    $company is the domain-resolved company when one is available (the
    common case — ResolveCompanyFromDomain binds it before any portal
    page mounts) so the page can still carry that company's own
    branding/contact details even when the specific link is invalid. It
    is null only when no company could be resolved at all (an unknown
    domain), which already falls through to a bare framework 404 in
    ResolveCompanyFromDomain itself — this view's own generic fallback
    below covers that same edge case if it's ever reached directly.

    A "Request new access link" self-service flow is real backend work
    (Phase 06/06B scope: a link model, rate limiting, a contact-scoped
    email delivery) that does not exist yet — deliberately left out per
    this phase's "pure visual restyle, no new logic" scope. The static
    contact details below are the safe fallback DESIGN §11 asks for in
    the meantime.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $company->name ?? config('app.name') }} — Link unavailable</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
        @tallStackUiStyle
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased dark:bg-gray-950! dark:text-gray-100!">
        <div class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                @if ($company)
                    <div class="mb-6 flex items-center justify-center gap-2.5">
                        @if ($company->getLogoDataUri())
                            <img src="{{ $company->getLogoDataUri() }}" alt="{{ $company->name }}" class="h-8 w-auto">
                        @endif
                        <span class="text-base font-semibold">{{ $company->name }}</span>
                    </div>
                @endif

                <x-card class="text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-icon name="link-slash" class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                    </div>

                    <h1 class="text-lg font-semibold tracking-tight">This link is no longer available</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        It may have expired, been replaced, or no longer applies. If you still need this
                        document, please get in touch and we'll help right away.
                    </p>

                    @if ($company && ($company->email || $company->phone))
                        <div class="mt-6 space-y-1 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                            <p class="font-medium text-gray-700 dark:text-gray-300">Need help?</p>
                            @if ($company->email)
                                <p>
                                    <a href="mailto:{{ $company->email }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $company->email }}</a>
                                </p>
                            @endif
                            @if ($company->phone)
                                <p>
                                    <a href="tel:{{ $company->phone }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $company->phone }}</a>
                                </p>
                            @endif
                        </div>
                    @endif
                </x-card>

                <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-600">
                    {{ $company->name ?? config('app.name') }}
                </p>
            </div>
        </div>

        @livewireScripts
        @tallStackUiScript
    </body>
</html>
