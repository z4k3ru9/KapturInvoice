<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Client Portal']]" title="Client Portal">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="client-portal">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Client portal</span>
        </x-slot:header>

        <div class="flex flex-col gap-5">
            <div>
                <x-toggle wire:model="portal_enabled" label="Enable client portal" />
                <p class="text-[11px] text-gray-400 mt-1">When off, invitation links still exist but resolve to a disabled-portal message.</p>
            </div>
            <div>
                <x-toggle wire:model="portal_allow_client_payments" label="Show the Pay section" />
                <p class="text-[11px] text-gray-400 mt-1">Whether a client sees the balance-due/Pay section on their portal invoice page (view-only — no real payment gateway is wired up yet).</p>
            </div>
            <div>
                <x-toggle wire:model="portal_require_signature" label="Require e-signature" />
                <p class="text-[11px] text-gray-400 mt-1">Whether the portal invoice page requires a client's e-signature before it's considered accepted.</p>
            </div>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
