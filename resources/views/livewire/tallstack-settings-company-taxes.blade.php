<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Company & Taxes']]" title="Company & Taxes">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="company-and-taxes">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Identity</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-2 gap-4">
            <x-input wire:model="name" label="Company name" required />
            <x-input wire:model="slug" label="Slug" required />
            <x-input wire:model="domain" label="Public homepage domain" hint="Resolves the public homepage/portal for this entity." />
            <x-input wire:model="email" label="Email" type="email" />
            <x-input wire:model="phone" label="Phone" />
            <x-input wire:model="tax_number" label="Tax ID" hint="Printed on invoice/credit PDFs." />
            <x-input wire:model="currency_code" label="Default currency" maxlength="3" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Branding</span>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Logo</label>
                @if ($existingLogoDataUri && ! $logo)
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <img src="{{ $existingLogoDataUri }}" alt="" class="w-full h-full object-cover">
                        </div>
                    </div>
                @endif
                <x-upload wire:model="logo" accept="image/jpeg,image/png,image/webp" tip="JPG, PNG, WEBP · max 2 MB." :preview="true" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Primary color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model="primary_color" class="h-9 w-9 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0 shrink-0">
                        <x-input wire:model="primary_color" placeholder="#RRGGBB" class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Secondary color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model="secondary_color" class="h-9 w-9 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0 shrink-0">
                        <x-input wire:model="secondary_color" placeholder="#RRGGBB" class="flex-1" />
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Document numbering</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <x-input wire:model="code" label="Company code" :disabled="$codesLocked"
                    :hint="$codesLocked ? 'Locked: this company has already issued a numbered document.' : 'Used in new document numbers, e.g. KJA-INV-2026090001. Locks after the first document is issued.'" />
            </div>
            <x-input wire:model="invoice_prefix" label="Invoice prefix" />
            <x-input wire:model="quote_prefix" label="Quote prefix" />
            <x-input wire:model="credit_prefix" label="Credit prefix" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Taxes</span>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            <div>
                <x-toggle wire:model.live="tax_enabled" label="Tax enabled" />
                <p class="text-[11px] text-gray-400 mt-1">When off, every invoice for this company is calculated with zero tax — see App\Services\Tax\TaxCalculationService.</p>
            </div>

            @if ($tax_enabled)
                <div class="grid sm:grid-cols-3 gap-4">
                    <x-input wire:model="standard_tax_rate" label="PPN rate" type="number" step="0.01" suffix="%" hint="Standard output/input VAT rate applied to standard-taxable lines." />
                    <x-input wire:model="dpp_factor_numerator" label="DPP Nilai Lain — numerator" type="number" hint="e.g. 11" />
                    <x-input wire:model="dpp_factor_denominator" label="DPP Nilai Lain — denominator" type="number" hint="e.g. 12" />
                </div>
            @endif
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
