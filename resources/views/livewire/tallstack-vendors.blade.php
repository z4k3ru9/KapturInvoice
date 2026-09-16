<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Vendors', 'icon' => 'building-storefront']]" title="Vendors">
        <x-slot:actions>
            <x-button text="New vendor" icon="plus" color="blue" sm class="h-9" wire:click="create" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Two stacked rows: the one currency (Rupiah) card gets its own full
         row, the two plain-count cards keep a tight row below — same
         col-span-full pattern as the Dashboard's own stat row; the outer
         wrapper's own classes are unchanged. Title + number only, no footer. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 gap-2.5">
        <div class="col-span-full grid grid-cols-1 gap-2.5">
            <x-stats scope="compact" title="Outstanding" icon="banknotes" color="amber">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['outstanding'] }}</span>
            </x-stats>
        </div>
        <div class="col-span-full grid grid-cols-2 gap-2.5">
            <x-stats scope="compact" title="Vendors" icon="building-storefront" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['total'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Bills" icon="clipboard-document-list" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['billsCount'] }}</span>
            </x-stats>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Supplier directory</span>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="w-full sm:w-64">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search name or email…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'name', 'label' => 'Vendor'],
            ['index' => 'email', 'label' => 'Email'],
            ['index' => 'phone', 'label' => 'Phone'],
            ['index' => 'purchase_orders_count', 'label' => 'POs', 'align' => 'right'],
            ['index' => 'bills_count', 'label' => 'Bills', 'align' => 'right'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$vendors" paginate loading>
            @interact('column_actions', $row, $company)
                @php
                    $extraActions = [
                        ['text' => 'View purchase orders', 'icon' => 'clipboard-document-list', 'href' => route('tallstack.vendor-purchase-orders', $company).'?vendor='.$row['id']],
                        ['text' => 'View bills', 'icon' => 'document-text', 'href' => route('tallstack.vendor-bills', $company).'?vendor='.$row['id']],
                        ['text' => 'Delete', 'icon' => 'trash', 'click' => 'delete('.$row['id'].')', 'confirm' => 'Delete this vendor?'],
                    ];
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="edit({{ $row['id'] }})" tooltip="Edit" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No vendors found.</x-slot:empty>
        </x-table>
    </x-card>

    <x-modal wire="showModal" title="{{ $editingId ? 'Edit vendor' : 'New vendor' }}" center="lg" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="name" label="Name" required />
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="email" label="Email address" type="email" />
                <x-input wire:model="phone" label="Phone" />
                <x-input wire:model="website" label="Website" class="sm:col-span-2" />
            </div>
            <div class="border-t border-gray-200 dark:border-gray-800! pt-4 grid sm:grid-cols-2 gap-4">
                <x-input wire:model="address_line_1" label="Address line 1" class="sm:col-span-2" />
                <x-input wire:model="address_line_2" label="Address line 2" class="sm:col-span-2" />
                <x-input wire:model="city" label="City" />
                <x-input wire:model="state" label="State" />
                <x-input wire:model="postal_code" label="Postal code" />
                <x-input wire:model="country_code" label="Country code" maxlength="2" />
            </div>
            <x-editor wire:model="notes" label="Notes" min-height="8rem" max-height="18rem"
                :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showModal', false)" />
            <x-button text="Save" color="blue" wire:click="save" />
        </x-slot:footer>
    </x-modal>
</div>
