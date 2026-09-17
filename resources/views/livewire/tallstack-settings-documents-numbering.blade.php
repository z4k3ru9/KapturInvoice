<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Documents & Numbering']]" title="Documents & Numbering">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="documents-numbering">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Document code & prefixes</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-3 gap-5">
            <div>
                @if ($codesLocked)
                    <x-input wire:model="code" label="Company code" disabled
                        hint="Locked: this company has already issued a numbered document." />
                @else
                    {{--
                        Live preview, computed entirely client-side — see
                        resources/views/components/tallstack/settings-tabs.blade.php's
                        own docblock for the vendor-interaction bug this
                        avoids relying on wire:model.live for (now fixed
                        at the shared-wrapper level, but this stays
                        client-side anyway since it's instant and needs
                        no round trip).
                    --}}
                    <div x-data="{ code: @js($code) }">
                        <x-input wire:model="code" label="Company code" x-on:input="code = $event.target.value" />
                        <span class="dark:text-gray-400! mt-1 block text-sm text-gray-500"
                            x-text="'Used in new document numbers, e.g. ' + (code || 'COM').toUpperCase() + '-INV-{{ now()->format('Ym') }}0001. Locks after the first document is issued.'"></span>
                    </div>
                @endif
            </div>
            <x-input wire:model="invoice_prefix" label="Invoice prefix" />
            <x-input wire:model="quote_prefix" label="Quote prefix" />
            <x-input wire:model="credit_prefix" label="Credit prefix" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Numbering sequences</span>
        </x-slot:header>

        <p class="text-[11px] text-gray-400 -mt-2 mb-3">Each sequence auto-increments independently per company. These are live counters — changing one affects the very next document's number.</p>

        <div class="grid sm:grid-cols-3 gap-5">
            <x-input wire:model="invoice_next_number" label="Invoice next number" type="number" min="1" required />
            <x-input wire:model="quote_next_number" label="Quote next number" type="number" min="1" required />
            <x-input wire:model="credit_next_number" label="Credit next number" type="number" min="1" required />
        </div>
    </x-card>

    <x-card minimize>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Document defaults</span>
        </x-slot:header>

        <p class="text-[11px] text-gray-400 -mt-2 mb-3">Applied to new invoices/quotes unless overridden on the document itself.</p>

        <div class="flex flex-col gap-5">
            <x-textarea wire:model="default_payment_terms" label="Default payment terms" rows="3" hint="Prefills the Terms field on new invoices, quotations, recurring invoice templates, and vendor purchase orders. Editable per document afterward." />

            <div class="grid sm:grid-cols-2 gap-5">
                <x-select.styled wire:model="default_tax_rate_1_id" label="Default tax 1" :options="$this->taxRateOptions" searchable clearable />
                <x-select.styled wire:model="default_tax_rate_2_id" label="Default tax 2" :options="$this->taxRateOptions" searchable clearable />
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <x-input wire:model="default_expire_after_days" label="Default expire after (days)" type="number" min="1" max="3650" placeholder="14"
                    hint="Prefills a new quotation's Valid until date as today plus this many days — freely editable per quotation afterward. Leave blank to use the system default of 14 days." />
            </div>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
