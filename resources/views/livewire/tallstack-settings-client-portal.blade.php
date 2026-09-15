<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Client Portal']]" title="Client Portal">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="client-portal">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Client portal</span>
        </x-slot:header>

        <div>
            <x-toggle wire:model="portal_enabled" label="Enable client portal" />
            <p class="text-[11px] text-gray-400 mt-1">When off, invitation links still exist but resolve to a disabled-portal message.</p>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
