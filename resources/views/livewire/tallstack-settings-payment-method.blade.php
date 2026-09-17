<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Payment Method']]" title="Payment Method" />

    <x-tallstack.settings-tabs :company="$company" active="payment-method">
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Bank accounts</span>
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
    </x-tallstack.settings-tabs>

    <x-modal wire="showBankAccountModal" :title="$editingBankAccountId ? 'Edit bank account' : 'Add bank account'" center="sm">
        <div class="grid sm:grid-cols-2 gap-5">
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
