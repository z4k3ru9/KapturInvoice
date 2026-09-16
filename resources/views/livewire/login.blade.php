{{--
    x-init here fires Passkeys.autofill() (conditional UI / WebAuthn
    "conditional mediation") the moment this page loads — if the
    browser/OS already has a passkey saved for this site, it surfaces as
    a suggestion right in the email field's own native autofill dropdown
    (anchored by autocomplete="username webauthn" below), with no
    separate button click needed at all. Falls through silently
    (`undefined`) when unsupported, cancelled, or no saved passkey
    exists — the password form and the explicit "Sign in with a
    passkey" button underneath both still work exactly the same either
    way; this is a convenience layered on top, not a required path.
--}}
<div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-16"
     x-data
     x-init="window.Passkeys?.autofill().then((res) => { if (res) window.location.href = res.redirect || '/'; }).catch(() => {})">
    <div class="flex flex-col items-center">
        {{--
            KapturInvoice's own generic mark — no tenant logo, since login
            happens before any tenant context exists (App\Http\Middleware\
            ApplyCompanyBrand only runs inside Filament's tenant-scoped
            middleware, and there's no resolved Company here at all).
        --}}
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-gray-900 text-sm font-bold text-white dark:bg-gray-100! dark:text-gray-900!">
            K
        </span>
        <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100!">Sign in to KapturInvoice</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400!">Enter your credentials to continue.</p>
    </div>

    <x-card class="mt-6">
        @error('authentication')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900! dark:bg-red-950! dark:text-red-300!">
                {{ $message }}
            </div>
        @enderror

        <form wire:submit="login" class="space-y-4">
            <x-input wire:model="email" type="email" label="Email" autocomplete="username webauthn" autofocus required />

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200!">Password</label>
                    {{--
                        This app has no working password-reset flow yet
                        (docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md
                        §24) — a clearly-labeled, disabled placeholder
                        rather than a link that goes nowhere.
                    --}}
                    <span class="cursor-not-allowed text-xs text-gray-400 dark:text-gray-600!" title="Password reset isn't available yet — contact your Owner/Admin.">
                        Forgot password?
                    </span>
                </div>
                <x-input id="password" wire:model="password" type="password" autocomplete="current-password" required />
            </div>

            <x-checkbox wire:model="remember" label="Remember me" />

            <x-button type="submit" text="Log in" color="blue" class="w-full justify-center" wire:loading.attr="disabled" wire:target="login" />
        </form>

        {{--
            Passkeys — an additional sign-in method, never a replacement
            for the password form above (App\Models\User implements
            Laravel\Passkeys\Contracts\PasskeyUser; a user registers one
            from the "Passkeys" section under their account menu once
            logged in — see resources/views/livewire/settings/passkeys.blade.php).
            `Passkeys.verify()` (window.Passkeys, resources/js/app.js)
            runs the full WebAuthn assertion ceremony client-side and
            POSTs it to the package's own `/passkeys/login` route, which
            authenticates the user server-side and returns where to send
            them next — this component has no server-side passkey-login
            method of its own to call.
        --}}
        <div x-data="{ busy: false, error: null, supported: false }" x-init="supported = window.Passkeys?.isSupported() ?? false">
            <template x-if="supported">
                <div>
                    <div class="my-4 flex items-center gap-3">
                        <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800!"></div>
                        <span class="text-xs text-gray-400 dark:text-gray-500!">or</span>
                        <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800!"></div>
                    </div>

                    <x-button
                        type="button"
                        text="Sign in with a passkey"
                        icon="finger-print"
                        color="gray"
                        class="w-full justify-center"
                        x-bind:disabled="busy"
                        x-on:click="
                            busy = true; error = null;
                            window.Passkeys.verify()
                                .then((res) => { window.location.href = res.redirect || '/'; })
                                .catch((err) => { busy = false; error = err?.message || 'Could not sign in with a passkey.'; })
                        "
                    />
                    <p x-show="error" x-cloak x-text="error" class="mt-2 text-center text-sm text-red-600 dark:text-red-400!"></p>
                </div>
            </template>
        </div>
    </x-card>

    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400!">
        Need a company? Log in, then you'll be guided to create one.
    </p>
</div>
