<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Formatting']]" title="Formatting">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="formatting">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Formatting</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-2 gap-5">
            <x-select.styled wire:model="currency_code" label="Default currency" searchable
                :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->all()"
                hint="Used for new invoices/quotations/jobs unless overridden per document." />
            <x-select.styled wire:model="timezone" label="Timezone" searchable required
                :options="$timezones->map(fn ($tz) => ['label' => $tz, 'value' => $tz])->all()" />
            <x-select.styled wire:model="default_document_language" label="Default document language" required
                :options="[
                    ['label' => 'Bahasa Indonesia', 'value' => 'id'],
                    ['label' => 'English', 'value' => 'en'],
                ]"
                hint="Every printed document (invoice, quotation, receipt, etc.) falls back to this unless it has its own language set — see App\Models\Invoice::resolveDocumentLanguage()." />
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
