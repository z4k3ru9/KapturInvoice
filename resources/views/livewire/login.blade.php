<div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-16">
    <div class="flex flex-col items-center">
        {{--
            KapturInvoice's own generic mark — no tenant logo, since login
            happens before any tenant context exists (App\Http\Middleware\
            ApplyCompanyBrand only runs inside Filament's tenant-scoped
            middleware, and there's no resolved Company here at all).
        --}}
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-gray-900 text-sm font-bold text-white dark:bg-gray-100 dark:text-gray-900">
            K
        </span>
        <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Sign in to KapturInvoice</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter your credentials to continue.</p>
    </div>

    <x-card class="mt-6">
        @error('authentication')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                {{ $message }}
            </div>
        @enderror

        <form wire:submit="login" class="space-y-4">
            <x-input wire:model="email" type="email" label="Email" autocomplete="username" autofocus required />

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Password</label>
                    {{--
                        This app has no working password-reset flow yet
                        (docs/rebuild/outputs/27-filament-parity-gap-prompts.md
                        §24) — a clearly-labeled, disabled placeholder
                        rather than a link that goes nowhere.
                    --}}
                    <span class="cursor-not-allowed text-xs text-gray-400 dark:text-gray-600" title="Password reset isn't available yet — contact your Owner/Admin.">
                        Forgot password?
                    </span>
                </div>
                <x-input id="password" wire:model="password" type="password" autocomplete="current-password" required />
            </div>

            <x-checkbox wire:model="remember" label="Remember me" />

            <x-button type="submit" text="Log in" color="blue" class="w-full justify-center" wire:loading.attr="disabled" wire:target="login" />
        </form>
    </x-card>

    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
        Need a company? Log in, then you'll be guided to create one.
    </p>
</div>
