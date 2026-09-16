<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Passkeys']]" title="Passkeys" />

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div>
                    <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Your passkeys</div>
                    <div class="text-xs text-gray-400">Sign in with your device's fingerprint, face, or screen lock instead of typing your password. Your password still works — a passkey is an additional way in, not a replacement.</div>
                </div>
            </div>
        </x-slot:header>

        {{--
            Registration is entirely client-side: window.Passkeys.register()
            (resources/js/app.js) runs the real WebAuthn ceremony in the
            browser (a fingerprint/face/screen-lock prompt — nothing this
            page can simulate or fake) and POSTs the result straight to
            the package's own /user/passkeys route, which persists it.
            refreshPasskeys() just re-renders this list afterward.
        --}}
        <div x-data="{
                busy: false,
                error: null,
                showNameModal: false,
                pendingName: '',
                supported: false,
             }"
             x-init="supported = window.Passkeys?.isSupported() ?? false"
             class="flex flex-col gap-4">
            <template x-if="!supported">
                <p class="text-sm text-amber-600 dark:text-amber-400!">This browser or device doesn't support passkeys.</p>
            </template>

            <template x-if="supported">
                <div>
                    <x-button
                        type="button"
                        text="Add a passkey"
                        icon="plus"
                        color="blue"
                        sm
                        x-on:click="pendingName = ''; showNameModal = true"
                    />
                    <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400!"></p>
                </div>
            </template>

            {{-- A short, plain Alpine prompt for the passkey's own label
                 (e.g. "MacBook Pro", "Work iPhone") — not a full
                 <x-modal>/Livewire round trip, since nothing here needs
                 server state until the ceremony itself completes. --}}
            <div x-show="showNameModal" x-cloak x-transition
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 x-on:click.self="showNameModal = false"
                 x-on:keydown.escape.window="showNameModal = false">
                {{--
                    `x-on:click.self` on the backdrop (fires only when the
                    click target IS this exact element), not
                    `x-on:click.outside` on the card — the latter's
                    document-level listener also caught the SAME click
                    that opened the modal (the "Add a passkey" button is
                    "outside" the card too), closing it in the same tick
                    it opened. Confirmed live: Alpine's own data stack
                    showed `showNameModal: true` right after the click,
                    but the element's computed style was still
                    `display: none` — this was that race, not a
                    reactivity bug.
                --}}
                <div class="w-full max-w-sm rounded-lg bg-white dark:bg-gray-900! p-5 shadow-xl">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100!">Name this passkey</h3>
                    <p class="mt-1 text-xs text-gray-400">So you can tell your devices apart later.</p>
                    <input type="text" x-model="pendingName" placeholder="e.g. MacBook Pro" x-on:keydown.enter="$refs.confirmAdd.click()"
                           class="mt-3 w-full h-9 text-sm rounded-lg border-gray-200 dark:border-gray-800! dark:bg-gray-950! dark:text-gray-100! focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
                    <div class="mt-4 flex justify-end gap-2">
                        <x-button type="button" text="Cancel" color="gray" sm x-on:click="showNameModal = false" />
                        <x-button type="button" text="Continue" color="blue" sm x-ref="confirmAdd"
                            x-bind:disabled="busy || !pendingName.trim()"
                            x-on:click="
                                busy = true; error = null;
                                window.Passkeys.register({ name: pendingName.trim() })
                                    .then(() => { showNameModal = false; $wire.refreshPasskeys(); })
                                    .catch((err) => { error = err?.message || 'Could not add this passkey.'; })
                                    .finally(() => { busy = false; })
                            " />
                    </div>
                </div>
            </div>
        </div>

        <x-table :headers="[
            ['index' => 'name', 'label' => 'Name', 'sortable' => false],
            ['index' => 'created_at', 'label' => 'Added', 'sortable' => false],
            ['index' => 'last_used_at', 'label' => 'Last used', 'sortable' => false],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$passkeys" class="mt-4">
            @interact('column_actions', $row)
                <div class="flex items-center justify-end">
                    <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" tooltip="Remove"
                        wire:click="delete({{ $row['id'] }})" wire:confirm="Remove this passkey? You'll need another way to sign in if it's your only one." />
                </div>
            @endinteract

            <x-slot:empty>No passkeys added yet.</x-slot:empty>
        </x-table>
    </x-card>
</div>
