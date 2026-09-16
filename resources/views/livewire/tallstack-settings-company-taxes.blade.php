<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Company & Taxes']]" title="Company & Taxes">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="company-and-taxes">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Identity</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-2 gap-4">
            <x-input wire:model="name" label="Company name" required />
            <x-input wire:model="slug" label="Slug" required />
            <x-input wire:model="domain" label="Public homepage domain" hint="Resolves the public homepage/portal for this entity." />
            <x-input wire:model="email" label="Email" type="email" />
            <x-input wire:model="phone" label="Phone" />
            <x-input wire:model="tax_number" label="Tax ID" hint="Printed on invoice/credit PDFs." />
            <x-select.styled wire:model="currency_code" label="Default currency" searchable
                :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->all()" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Branding</span>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            <div>
                <x-label label="Logo" />
                @if ($existingLogoDataUri && ! $logo)
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700!">
                            <img src="{{ $existingLogoDataUri }}" alt="" class="w-full h-full object-cover">
                        </div>
                    </div>
                @endif
                <x-upload wire:model="logo" accept="image/jpeg,image/png,image/webp" tip="JPG, PNG, WEBP · max 2 MB." :preview="true" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-color wire:model="primary_color" label="Primary color" placeholder="#RRGGBB" clearable />
                <x-color wire:model="secondary_color" label="Secondary color" placeholder="#RRGGBB" clearable />
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Document numbering</span>
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
            <div class="flex items-center justify-between w-full">
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Payment method</span>
                <x-button text="Add bank account" icon="plus" color="gray" sm class="h-9" wire:click="openCreateBankAccountModal" />
            </div>
        </x-slot:header>

        {{-- Printed as a "Payment Method" section on the Invoice PDF
             (resources/views/pdf/invoice.blade.php) — a company may list
             more than one bank account, e.g. two different banks. --}}
        <x-list :items="$bankAccounts" compact>
            @interact('item_menu', $item)
                <x-dropdown.items text="Edit" icon="pencil" wire:click="openEditBankAccountModal({{ $item['id'] }})" />
                <x-dropdown.items text="Delete" icon="trash" separator wire:click="deleteBankAccount({{ $item['id'] }})" wire:confirm="Remove this bank account? It will no longer be printed on invoices." />
            @endinteract

            <x-slot:empty>No bank accounts yet — add one to have it printed on invoices.</x-slot:empty>
        </x-list>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Taxes</span>
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

    <x-modal wire="showBankAccountModal" :title="$editingBankAccountId ? 'Edit bank account' : 'Add bank account'" center="sm">
        <div class="grid sm:grid-cols-2 gap-4">
            <x-input wire:model="ba_bank_name" label="Bank name" required />
            <x-input wire:model="ba_account_name" label="Account holder name" required />
            <x-input wire:model="ba_account_number" label="Account number" required />
            <x-input wire:model="ba_branch" label="Branch" />
            <x-input wire:model="ba_swift_code" label="SWIFT / BIC code" hint="Optional — for international transfers." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showBankAccountModal', false)" />
            <x-button text="Save" color="blue" wire:click="saveBankAccount" loading="saveBankAccount" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
