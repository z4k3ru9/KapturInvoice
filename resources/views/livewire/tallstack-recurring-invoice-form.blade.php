<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.recurring-invoices', $company)],
            ['label' => 'Recurring Invoices', 'url' => route('tallstack.recurring-invoices', $company)],
            ['label' => $invoice ? ($invoice->number ?? 'Schedule') : 'New'],
        ]"
        :title="$invoice ? ($invoice->number ?? 'Recurring invoice') : 'New recurring invoice'"
    >
        <x-slot:actions>
            @if ($invoice)
                {{-- color="green" — the same forward/positive action color
                     Invoices' own "Issue" button uses. This calls
                     App\Services\InvoiceDuplicator::generateRecurringInstance()
                     unmodified — the exact action the Filament table's own
                     "Generate now" row action calls. --}}
                <x-button text="Generate now" icon="bolt" color="green" sm class="h-9" wire:click="generateNow" wire:confirm="Generate a new invoice from this schedule now?" loading="generateNow" spinner="dots" />
            @endif
            {{-- color="blue" — see the Invoice form's own "Save" button for
                 the standardized general-action color reasoning. --}}
            <x-button text="Save" icon="document-check" color="blue" sm class="h-9" wire:click="save" loading="save" spinner="dots" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
        <div class="flex flex-col gap-4">
            {{-- Client & schedule — prompt 16's own field set (Client,
                 Frequency, Start/End date) plus the rest of the fields the
                 real Invoice row/InvoiceForm carries for a recurring
                 template (number, PO, currency, discount) so nothing the
                 Filament resource has is dropped. --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Client &amp; schedule</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-select.styled wire:model.live="client_id" label="Client" searchable required
                            :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
                    </div>
                    <x-select.styled wire:model="recurring_frequency" label="Frequency" required
                        :options="[
                            ['label' => 'Weekly', 'value' => 'weekly'],
                            ['label' => 'Monthly', 'value' => 'monthly'],
                            ['label' => 'Quarterly', 'value' => 'quarterly'],
                            ['label' => 'Annually', 'value' => 'annually'],
                        ]" />
                    <div class="flex items-end pb-2">
                        <x-toggle wire:model="auto_bill" label="Auto-bill" />
                    </div>
                    <x-date wire:model="recurring_start_date" label="Start date" />
                    <div class="flex flex-col gap-1.5">
                        <x-date wire:model="recurring_end_date" label="End date" :disabled="$no_end_date" />
                        <x-toggle wire:model.live="no_end_date" label="No end date" />
                    </div>
                </div>
            </x-card>

            {{-- Invoice terms — same remaining InvoiceForm fields a
                 recurring template also carries, kept in their own card so
                 the schedule fields above stay the visual focus, matching
                 prompt 16's own layout emphasis. --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Invoice terms</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                    <x-select.styled wire:model="pricing_mode" label="Pricing mode" required
                        :options="collect($pricingModes)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
                    <x-input wire:model="po_number" label="PO number" />
                    <x-input wire:model="currency_code" label="Currency code" />
                    <x-input wire:model="discount" label="Discount" type="number" step="0.01" />
                    <div class="flex items-end pb-2">
                        <x-toggle wire:model="discount_is_percentage" label="Discount is a percentage" />
                    </div>
                </div>
            </x-card>

            {{-- Line items — identical pattern to the plain Invoice form. --}}
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Line items</span>
                        @if ($invoice)
                            <x-button text="Add line item" icon="plus" color="blue" sm wire:click="addItem" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $invoice)
                    <p class="text-sm text-gray-400">Save the recurring invoice first to add line items.</p>
                @else
                    <x-table :headers="[
                        ['index' => 'image', 'label' => '', 'sortable' => false],
                        ['index' => 'title', 'label' => 'Item', 'sortable' => false],
                        ['index' => 'quantity', 'label' => 'Qty', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'unit_cost', 'label' => 'Unit cost', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'taxes', 'label' => 'Taxes', 'sortable' => false],
                        ['index' => 'line_total', 'label' => 'Line total', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'actions', 'label' => '', 'sortable' => false],
                    ]" :rows="$items">
                        @interact('column_image', $row)
                            @if ($row['image'])
                                <img src="{{ $row['image'] }}" alt="" class="w-8 h-8 rounded object-cover">
                            @endif
                        @endinteract
                        @interact('column_taxes', $row)
                            @forelse ($row['taxes'] as $tax)
                                <x-badge text="{{ $tax }}" color="gray" sm />
                            @empty
                                <x-badge text="No tax" color="gray" sm />
                            @endforelse
                        @endinteract
                        @interact('column_actions', $row)
                            <div class="flex items-center justify-end gap-2">
                                <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editItem({{ $row['id'] }})" />
                                <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteItem({{ $row['id'] }})" wire:confirm="Remove this line item?" />
                            </div>
                        @endinteract
                        <x-slot:empty>No line items yet.</x-slot:empty>
                    </x-table>
                @endif
            </x-card>

            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Notes</span>
                </x-slot:header>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-editor wire:model="terms" label="Terms" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
                    <x-editor wire:model="public_notes" label="Public notes" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
                    <x-editor wire:model="private_notes" label="Private notes" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
                    <x-editor wire:model="footer" label="Footer" min-height="4rem" max-height="8rem"
                        :toolbar="['bold', 'italic', 'clear-format', 'undo', 'redo']" />
                </div>
            </x-card>
        </div>

        {{-- Financial summary + generated invoices --}}
        <div class="flex flex-col gap-4">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Financial summary</span>
                </x-slot:header>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                        <span class="font-semibold tabular-nums">{{ $subtotal }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Discount</span>
                        <span class="tabular-nums">{{ $discount_is_percentage ? $discount.'%' : \App\Support\Dashboard\Money::format($discount, $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Tax</span>
                        <span class="tabular-nums">{{ $taxTotal }}</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-gray-900 dark:text-gray-100">Total per cycle</span>
                        <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Recomputed automatically from the line items above — this is the amount each generated invoice will carry, via App\Services\InvoiceTotalsCalculator.</p>
                </div>
            </x-card>

            {{-- Generated invoices — every real Invoice row this template
                 has produced (`recurring_template_id`), each linking to its
                 own TallStack invoice edit page — no new filter/route was
                 added to App\Livewire\TallStackInvoices for this, since a
                 generated instance is an ordinary, already-linkable
                 Invoice row. --}}
            {{-- x-card only forwards x-on:* attributes onto its wrapper, so
                 the "View generated invoices" register anchor targets this
                 plain wrapping div instead of the card element itself. --}}
            <div id="generated-invoices">
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Generated invoices</span>
                    </x-slot:header>

                    @if ($generatedInvoices->isEmpty())
                        <p class="text-sm text-gray-400">No invoices generated yet.</p>
                    @else
                        <div class="flex flex-col gap-2">
                            @foreach ($generatedInvoices as $gen)
                                <a href="{{ route('tallstack.invoices.edit', [$company, $gen['id']]) }}" class="flex items-center justify-between gap-2 text-sm py-1.5 border-b border-gray-100 dark:border-gray-800 last:border-b-0 hover:opacity-80">
                                    <div class="flex flex-col">
                                        <span class="font-mono text-xs font-medium text-blue-600 dark:text-blue-400">{{ $gen['number'] }}</span>
                                        <span class="text-[11px] text-gray-400">{{ $gen['invoice_date'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="tabular-nums font-semibold">{{ $gen['total'] }}</span>
                                        <x-badge text="{{ $gen['status_label'] }}" :color="$gen['status_color']" sm />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>
        </div>
    </div>

    {{-- Line item modal — identical to the plain Invoice form's. --}}
    <x-modal wire="showItemModal" title="{{ $editingItemId ? 'Edit line item' : 'Add line item' }}" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model.live="item_product_id" label="Product (optional)" searchable clearable
                :options="$products->map(fn ($p) => ['label' => $p->name, 'value' => (string) $p->id])->all()" />
            <x-input wire:model="item_title" label="Title" required />
            <x-textarea wire:model="item_description" label="Description" rows="2" />
            <div class="grid grid-cols-2 gap-4">
                <x-input wire:model="item_quantity" label="Quantity" type="number" step="0.0001" />
                <x-input wire:model="item_unit_cost" label="Unit cost" type="number" step="0.01" />
                <x-input wire:model="item_discount" label="Discount" type="number" step="0.01" />
                <div class="flex items-end pb-2">
                    <x-toggle wire:model="item_discount_is_percentage" label="Discount is a percentage" />
                </div>
            </div>
            <x-select.styled wire:model="item_tax_rate_ids" label="Taxes" :multiple="true" searchable
                :options="$taxRates->map(fn ($t) => ['label' => $t->name, 'value' => (string) $t->id])->all()" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showItemModal', false)" />
            <x-button text="Save line item" color="blue" wire:click="saveItem" />
        </x-slot:footer>
    </x-modal>
</div>
