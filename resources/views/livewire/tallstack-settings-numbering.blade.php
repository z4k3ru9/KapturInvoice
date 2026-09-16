<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Numbering']]" title="Numbering">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="numbering">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Numbering sequences</span>
        </x-slot:header>

        <p class="text-[11px] text-gray-400 -mt-2 mb-3">Each sequence auto-increments independently per company. These are live counters — changing one affects the very next document's number.</p>

        <div class="grid sm:grid-cols-3 gap-4">
            <x-input wire:model="invoice_next_number" label="Invoice next number" type="number" min="1" required />
            <x-input wire:model="quote_next_number" label="Quote next number" type="number" min="1" required />
            <x-input wire:model="credit_next_number" label="Credit next number" type="number" min="1" required />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Defaults</span>
        </x-slot:header>

        <p class="text-[11px] text-gray-400 -mt-2 mb-3">Applied to new invoices/quotes unless overridden on the document itself.</p>

        <div class="flex flex-col gap-4">
            <x-textarea wire:model="default_payment_terms" label="Default payment terms" rows="3" hint="Prefills the Terms field on new invoices, quotations, recurring invoice templates, and vendor purchase orders. Editable per document afterward." />

            <div class="grid sm:grid-cols-2 gap-4">
                <x-select.styled wire:model="default_tax_rate_1_id" label="Default tax 1" :options="$this->taxRateOptions" searchable clearable />
                <x-select.styled wire:model="default_tax_rate_2_id" label="Default tax 2" :options="$this->taxRateOptions" searchable clearable />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="default_expire_after_days" label="Default expire after (days)" type="number" min="1" max="3650" placeholder="14"
                    hint="Prefills a new quotation's Valid until date as today plus this many days — freely editable per quotation afterward. Leave blank to use the system default of 14 days." />
            </div>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
