<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Identity']]" title="Identity">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="identity">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Identity</span>
        </x-slot:header>

        <div class="flex flex-col gap-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <x-input wire:model="name" label="Company name" required />
                <x-input wire:model="slug" label="Slug" required />
                <x-input wire:model="domain" label="Public homepage domain" />
                <x-input wire:model="email" label="Email" type="email" />
                <x-input wire:model="phone" label="Phone" />
                <x-input wire:model="tax_number" label="Tax ID" hint="Printed on invoice/credit PDFs." />
            </div>

            {{--
                Owner-only — App\Models\User::canAccessTenant() checks
                is_active unconditionally, even for a super-admin, so
                turning this off locks EVERYONE (including whoever just
                clicked it) out of this company immediately, not just
                eventually. wire:confirm is the client-side half of that
                warning; TallStackSettingsIdentity::save() re-checks the
                Owner role server-side before actually writing it.
            --}}
            <div class="border-t border-gray-200 dark:border-gray-800! pt-5">
                <x-toggle wire:model="is_active" label="Company active"
                    wire:confirm="Deactivating blocks login and portal access for EVERYONE at this company immediately, including you. Are you sure?" />
                <p class="text-[11px] text-gray-400 mt-1">Off blocks admin login and the public portal entirely for this company. Owner only.</p>
            </div>
        </div>
    </x-card>

    <x-card minimize>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Address</span>
        </x-slot:header>

        {{-- Printed on the public homepage's "Get in touch" strip
             (resources/views/livewire/home-page.blade.php) and every
             generated PDF's company header — same field set/validation as
             TallStackVendors's own address block. --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <x-input wire:model="address_line_1" label="Address line 1" class="sm:col-span-2" />
            <x-input wire:model="address_line_2" label="Address line 2" class="sm:col-span-2" />
            <x-input wire:model="city" label="City" />
            <x-input wire:model="state" label="State" />
            <x-input wire:model="postal_code" label="Postal code" />
            <x-input wire:model="country_code" label="Country code" maxlength="2" />
        </div>
    </x-card>

    <x-card minimize="mount">
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Preferences</span>
        </x-slot:header>

        <div class="max-w-xs">
            <x-select.styled wire:model="dashboard_refresh_seconds" label="Dashboard auto-refresh"
                :options="[
                    ['value' => 0, 'label' => 'Off — refresh manually'],
                    ['value' => 30, 'label' => 'Every 30 seconds'],
                    ['value' => 60, 'label' => 'Every minute'],
                    ['value' => 120, 'label' => 'Every 2 minutes'],
                    ['value' => 300, 'label' => 'Every 5 minutes'],
                ]" />
            <p class="text-[11px] text-gray-400 mt-1">How often the Dashboard's stats and chart pull fresh data on their own, in the background — applies to everyone viewing this company's dashboard.</p>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
