<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Clients']]" title="Clients">
        <x-slot:actions>
            {{-- color="blue", not "primary" — see app.blade.php's own
                 "+New" button for why. --}}
            <x-button text="New client" icon="plus" color="blue" sm class="h-9" wire:click="openCreateModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">All clients</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search name or email…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'name', 'label' => 'Name'],
            ['index' => 'email', 'label' => 'Email'],
            ['index' => 'phone', 'label' => 'Phone'],
            ['index' => 'currency_code', 'label' => 'Currency'],
            ['index' => 'balance_formatted', 'label' => 'Balance', 'align' => 'right'],
            ['index' => 'paid_to_date_formatted', 'label' => 'Paid to date', 'align' => 'right'],
            ['index' => 'tax_number', 'label' => 'Tax number'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$clients" paginate loading>
            @interact('column_name', $row, $company)
                <a href="{{ route('tallstack.clients.show', [$company, $row['id']]) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                    {{ $row['name'] }}
                </a>
            @endinteract

            {{-- Right-aligned, tabular numerals, red text when the client
                 owes money — matching prompt 10's own spec literally. --}}
            @interact('column_balance_formatted', $row)
                <span class="tabular-nums font-medium {{ $row['balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-200' }}">
                    {{ $row['balance_formatted'] }}
                </span>
            @endinteract

            @interact('column_paid_to_date_formatted', $row)
                <span class="tabular-nums text-gray-700 dark:text-gray-200">{{ $row['paid_to_date_formatted'] }}</span>
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.clients.show', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="View" />
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Edit" wire:click="openEditModal({{ $row['id'] }})" />
                </div>
            @endinteract

            <x-slot:empty>No clients found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Create/edit — Client stays modal-based (no dedicated Create/Edit
         page) exactly like the Filament resource
         (docs/filament-admin-layout-design.md §8). --}}
    <x-modal wire="showClientModal" :title="$editingClientId ? 'Edit client' : 'New client'" center="lg">
        <div class="flex flex-col gap-5">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Client</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="name" label="Name" class="sm:col-span-2" />
                    <x-input wire:model="email" label="Email address" />
                    <x-input wire:model="phone" label="Phone" />
                    <x-input wire:model="website" label="Website" />
                    <x-select.styled wire:model="currency_code" label="Currency" searchable
                        :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->all()" />
                    <x-input wire:model="tax_number" label="Tax number" />
                    <x-input wire:model="id_number" label="ID number" />
                    <x-input wire:model="legacy_client_id" label="Legacy InvoiceNinja client id" hint="For import traceability." />
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Billing defaults</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Prefilled onto new invoices for this client (editable per invoice).</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="default_discount" label="Default discount" type="number" step="0.01" />
                    <div class="flex items-end pb-2">
                        <x-toggle wire:model="default_discount_is_percentage" label="Discount is a percentage" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Address</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="address_line_1" label="Address line 1" class="sm:col-span-2" />
                    <x-input wire:model="address_line_2" label="Address line 2" class="sm:col-span-2" />
                    <x-input wire:model="city" label="City" />
                    <x-input wire:model="state" label="State" />
                    <x-input wire:model="postal_code" label="Postal code" />
                    <x-input wire:model="country_code" label="Country code" maxlength="2" />
                </div>
            </div>

            <x-textarea wire:model="notes" label="Notes" rows="3" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showClientModal', false)" />
            <x-button text="Save" color="blue" wire:click="save" />
        </x-slot:footer>
    </x-modal>
</div>
