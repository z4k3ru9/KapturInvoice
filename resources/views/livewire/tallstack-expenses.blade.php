<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Expenses']]" title="Expenses">
        <x-slot:actions>
            <x-button text="New expense" icon="plus" color="blue" sm class="h-9" wire:click="openCreateModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">All expenses</span>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="w-full sm:w-56">
                        <x-select.styled wire:model.live="categoryFilter" placeholder="All categories" clearable
                            :options="$categories->map(fn ($name, $id) => ['label' => $name, 'value' => (string) $id])->values()->all()" />
                    </div>
                    <div class="w-full sm:w-64">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search vendor or reference…" icon="magnifying-glass" clearable />
                    </div>
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'expense_date', 'label' => 'Date'],
            ['index' => 'vendor', 'label' => 'Vendor'],
            ['index' => 'category', 'label' => 'Category'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'subtotal_formatted', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'should_be_invoiced', 'label' => 'Should be invoiced'],
            ['index' => 'transaction_reference', 'label' => 'Transaction reference'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$expenses" paginate loading>
            @interact('column_client', $row, $company)
                @if ($row['client'])
                    <a href="{{ route('tallstack.clients.show', [$company, $row['client_id']]) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                        {{ $row['client'] }}
                    </a>
                @else
                    <span class="text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_subtotal_formatted', $row)
                <span class="tabular-nums font-medium text-gray-700 dark:text-gray-200">{{ $row['subtotal_formatted'] }}</span>
            @endinteract

            @interact('column_should_be_invoiced', $row)
                @if ($row['should_be_invoiced'])
                    <x-icon name="check-circle" class="h-5 w-5 text-green-600 dark:text-green-400" />
                @else
                    <x-icon name="x-circle" class="h-5 w-5 text-gray-300 dark:text-gray-600" />
                @endif
            @endinteract

            @interact('column_actions', $row)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Edit" wire:click="openEditModal({{ $row['id'] }})" />
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        <x-dropdown.items text="Delete" icon="trash" wire:click="delete({{ $row['id'] }})" wire:confirm="Delete this expense?" />
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No expenses recorded yet — New expense to add one.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Create/edit — Expense follows this app's small-resource modal
         convention (see TallStackClients/TallStackVendors) rather than
         the Filament resource's own dedicated Create/Edit pages; matches
         the fetched Stitch mockup, which only ever shows a modal. --}}
    <x-modal wire="showExpenseModal" :title="$editingExpenseId ? 'Edit expense' : 'New expense'" center="lg" scrollable>
        <div class="flex flex-col gap-5">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Expense</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-select.styled wire:model="vendor_id" label="Vendor" searchable clearable
                        :options="$vendors->map(fn ($name, $id) => ['label' => $name, 'value' => (string) $id])->values()->all()" />
                    <x-select.styled wire:model="expense_category_id" label="Expense category" searchable clearable
                        :options="$categories->map(fn ($name, $id) => ['label' => $name, 'value' => (string) $id])->values()->all()" />
                    <x-date wire:model="expense_date" label="Expense date" />
                    <x-select.styled wire:model="currency_code" label="Currency" searchable
                        :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->values()->all()" />
                    <x-input wire:model="exchange_rate" label="Exchange rate" type="number" step="0.0001" />
                    <x-input wire:model="subtotal" label="Subtotal" type="number" step="0.01" required />
                    <x-select.styled wire:model="tax_rate_ids" label="Taxes" :multiple="true" searchable
                        :options="$taxRates->map(fn ($name, $id) => ['label' => $name, 'value' => (string) $id])->values()->all()" class="sm:col-span-2" />
                    <x-input wire:model="transaction_reference" label="Transaction reference" class="sm:col-span-2" />
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-800 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Rebilling</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-toggle wire:model.live="should_be_invoiced" label="Rebill to client" class="sm:col-span-2" />

                    @if ($should_be_invoiced)
                        <x-select.styled wire:model.live="client_id" label="Client" searchable clearable
                            :options="$clients->map(fn ($name, $id) => ['label' => $name, 'value' => (string) $id])->values()->all()" />
                        <x-select.styled wire:model="invoice_id" label="Related invoice" searchable clearable
                            :options="$invoiceOptions->map(fn ($number, $id) => ['label' => $number, 'value' => (string) $id])->values()->all()" />
                    @endif
                </div>
            </div>

            @if ($editingExpenseId)
                <div class="border-t border-gray-200 dark:border-gray-800 pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Totals</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Recomputed automatically from the amount and taxes above.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input :value="$tax_total" label="Tax total" disabled />
                        <x-input :value="$total" label="Total" disabled />
                    </div>
                </div>
            @endif

            <x-editor wire:model="private_notes" label="Private notes" min-height="8rem" max-height="18rem"
                :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />

            {{--
                Documents — only reachable while editing (a Document needs
                a real expense row to attach to). See
                App\Livewire\Concerns\ManagesDocuments; same shape as the
                Invoice/Quotation forms' own Documents card.
            --}}
            @if ($editingExpenseId)
                <div class="border-t border-gray-200 dark:border-gray-800 pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Documents</h3>
                    <div class="flex flex-col gap-3">
                        <x-upload wire:model="newDocument" label="Attach a document" tip="PDF, JPG or PNG up to 10MB" :preview="false" />
                        <x-button text="Upload" icon="arrow-up-tray" color="blue" sm wire:click="uploadDocument" />

                        <x-table :headers="[
                            ['index' => 'filename', 'label' => 'Filename', 'sortable' => false],
                            ['index' => 'size', 'label' => 'Size', 'sortable' => false],
                            ['index' => 'uploaded_by', 'label' => 'Uploaded by', 'sortable' => false],
                            ['index' => 'uploaded_at', 'label' => 'Uploaded at', 'sortable' => false],
                            ['index' => 'actions', 'label' => '', 'sortable' => false],
                        ]" :rows="$documents">
                            @interact('column_actions', $row)
                                <div class="flex items-center justify-end gap-2">
                                    <x-button icon="arrow-down-tray" sm color="gray" scope="icon-action" class="h-9 w-9" href="{{ route('documents.download', $row['id']) }}" target="_blank" tooltip="Download" />
                                    <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteDocument({{ $row['id'] }})" wire:confirm="Delete this document?" />
                                </div>
                            @endinteract
                            <x-slot:empty>No documents attached yet.</x-slot:empty>
                        </x-table>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showExpenseModal', false)" />
            <x-button text="Save" color="blue" wire:click="save" />
        </x-slot:footer>
    </x-modal>
</div>
