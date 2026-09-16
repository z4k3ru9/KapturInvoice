<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Tax Rates & Small Lookups']]" title="Tax Rates & Small Lookups">
        <x-slot:actions>
            @if ($tab === 'tax-rates' && $taxEnabled)
                <x-button text="New tax rate" icon="plus" color="blue" sm class="h-9" wire:click="openCreateTaxRateModal" />
            @elseif ($tab === 'expense-categories')
                <x-button text="New category" icon="plus" color="blue" sm class="h-9" wire:click="openCreateExpenseCategoryModal" />
            @elseif ($tab === 'task-statuses')
                <x-button text="New status" icon="plus" color="blue" sm class="h-9" wire:click="openCreateTaskStatusModal" />
            @endif
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="lookups">

    {{-- Tab strip — mirrors the fetched "Settings — Tax Rates & Small
         Lookups" Stitch mockup's own tab bar. This is a SECOND, nested
         level of tabs (tax-rates/expense-categories/task-statuses within
         the outer "Tax Rates & Lookups" settings tab) — its own
         server-driven wire:click="switchTab" mechanism, unrelated to and
         untouched by the Phase 12 outer settings-tabs wrapper above. --}}
    <div class="flex items-center gap-1.5 border-b border-gray-200 dark:border-gray-800!">
        @foreach ([
            'tax-rates' => 'Tax rates',
            'expense-categories' => 'Expense categories',
            'task-statuses' => 'Task statuses',
        ] as $key => $label)
            <button type="button" wire:click="switchTab('{{ $key }}')"
                    class="px-3 py-2 text-sm font-semibold border-b-2 -mb-px {{ $tab === $key ? 'border-[color:var(--ts-primary)] text-gray-900 dark:text-gray-100!' : 'border-transparent text-gray-500 dark:text-gray-400! hover:text-gray-700 dark:hover:text-gray-200!' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Tax Rates ------------------------------------------------------- --}}
    @if ($tab === 'tax-rates')
        <x-card>
            @if (! $taxEnabled)
                {{-- "Karunia Abadi's CompanyTaxSetting.tax_enabled is false
                     by design, this table should never invite adding
                     rates that will never apply" (prompt 13). --}}
                <div class="flex flex-col items-center justify-center text-center py-14 px-6">
                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800! grid place-items-center mb-3">
                        <x-icon name="receipt-percent" class="w-6 h-6 text-gray-400" />
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200!">Tax is disabled for this company</p>
                    <p class="text-xs text-gray-400 mt-1 max-w-sm">No tax rates are used — enable tax in Company & Taxes to configure rates.</p>
                </div>
            @else
                <x-list :items="$taxRates" searchable search-placeholder="Search tax rates…" compact>
                    @interact('item_menu', $item)
                        <x-dropdown.items text="Edit" icon="pencil" wire:click="openEditTaxRateModal({{ $item['id'] }})" />
                        @if ($item['usage'] > 0)
                            <x-dropdown.items text="In use — cannot delete" icon="lock-closed" separator />
                        @else
                            <x-dropdown.items text="Delete" icon="trash" separator wire:click="deleteTaxRate({{ $item['id'] }})" wire:confirm="Delete this tax rate?" />
                        @endif
                    @endinteract

                    <x-slot:empty>No tax rates yet.</x-slot:empty>
                </x-list>
            @endif
        </x-card>
    @endif

    {{-- Expense Categories ------------------------------------------------ --}}
    @if ($tab === 'expense-categories')
        <x-card>
            <x-list :items="$expenseCategories" searchable search-placeholder="Search expense categories…" compact>
                @interact('item_menu', $item)
                    <x-dropdown.items text="Edit" icon="pencil" wire:click="openEditExpenseCategoryModal({{ $item['id'] }})" />
                    <x-dropdown.items text="Delete" icon="trash" separator wire:click="deleteExpenseCategory({{ $item['id'] }})" wire:confirm="Delete this expense category?" />
                @endinteract

                <x-slot:empty>No expense categories yet.</x-slot:empty>
            </x-list>
        </x-card>
    @endif

    {{-- Task Statuses ------------------------------------------------------ --}}
    @if ($tab === 'task-statuses')
        <x-card>
            {{-- The fetched Stitch mockup sketches a color-swatch column,
                 but `task_statuses` has no color column (see
                 App\Models\TaskStatus's Fillable set and its own
                 "FROZEN — legacy" docblock) — deliberately not built
                 rather than inventing a field the schema doesn't have. --}}
            <x-list :items="$taskStatuses" searchable search-placeholder="Search task statuses…" compact>
                @interact('item_caption', $item)
                    Order: {{ $item['sort_order'] }}
                @endinteract

                @interact('item_menu', $item)
                    <x-dropdown.items text="Edit" icon="pencil" wire:click="openEditTaskStatusModal({{ $item['id'] }})" />
                    <x-dropdown.items text="Delete" icon="trash" separator wire:click="deleteTaskStatus({{ $item['id'] }})" wire:confirm="Delete this task status?" />
                @endinteract

                <x-slot:empty>No task statuses yet.</x-slot:empty>
            </x-list>
        </x-card>
    @endif

    {{-- Tax Rate modal — TaxRateForm's own field set. -------------------- --}}
    <x-modal wire="showTaxRateModal" title="{{ $editingTaxRateId ? 'Edit tax rate' : 'New tax rate' }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="tr_name" label="Name" required />
            <x-input wire:model="tr_rate" label="Rate" type="number" step="0.001" suffix="%" required />
            <x-toggle wire:model="tr_is_inclusive" label="Inclusive" hint="Whether this rate is already included in the price, rather than added on top." />
            <x-input wire:model="tr_legacy_tax_rate_id" label="Legacy tax rate ID" type="number" hint="Legacy InvoiceNinja tax_rate id, for import traceability." />
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showTaxRateModal', false)" />
            <x-button text="Save" icon="document-check" color="blue" wire:click="saveTaxRate" />
        </x-slot:footer>
    </x-modal>

    {{-- Expense Category modal --------------------------------------------- --}}
    <x-modal wire="showExpenseCategoryModal" title="{{ $editingExpenseCategoryId ? 'Edit expense category' : 'New expense category' }}" center="sm">
        <x-input wire:model="ec_name" label="Name" required />
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showExpenseCategoryModal', false)" />
            <x-button text="Save" icon="document-check" color="blue" wire:click="saveExpenseCategory" />
        </x-slot:footer>
    </x-modal>

    {{-- Task Status modal --------------------------------------------------- --}}
    <x-modal wire="showTaskStatusModal" title="{{ $editingTaskStatusId ? 'Edit task status' : 'New task status' }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="ts_name" label="Name" required />
            <x-input wire:model="ts_sort_order" label="Sort order" type="number" />
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showTaskStatusModal', false)" />
            <x-button text="Save" icon="document-check" color="blue" wire:click="saveTaskStatus" />
        </x-slot:footer>
    </x-modal>
    </x-tallstack.settings-tabs>
</div>
