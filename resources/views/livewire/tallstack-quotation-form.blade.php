<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.quotations', $company)],
            ['label' => 'Quotations', 'url' => route('tallstack.quotations', $company)],
            ['label' => $quotation ? $quotation->number : 'New'],
        ]"
        :title="$quotation ? $quotation->number : 'New quotation'"
    >
        @if ($quotation)
            <x-slot:badge>
                <x-badge text="{{ $quotation->status->getLabel() }}" :color="$statusColor" sm />
            </x-slot:badge>
        @endif
        <x-slot:actions>
            @if ($quotation)
                <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('quotations.pdf', $quotation) }}" target="_blank" color="gray" sm class="h-9" />
            @endif
            {{--
                color="blue" for Save — same reasoning as every other
                general-function button on these TALL-stack pages (see
                app.blade.php's "+New" button): "primary" is the tenant's
                own brand color, which for Karunia Abadi is red and reads
                as visually identical to the destructive-red buttons in
                the status bar below (Reject/Cancel quotation) — brand
                color is reserved for identity chrome, not workflow
                buttons.
            --}}
            {{-- icon="document-check" — closest available Heroicon to a
                 floppy-disk/save glyph; this set has no literal one. --}}
            <x-button text="Save" icon="document-check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Status action bar — every transition here calls the exact same
        App\Actions\Sales\* class the Filament table row actions use; no
        status is ever set directly from this UI.

        Colors are a fixed, standardized semantic palette — green for a
        forward/positive move (Approve, Accept, Create job), blue for a
        neutral in-progress action (Send), gray for a passive/neutral one
        (Mark expired), red for anything destructive/terminal (Reject,
        Cancel quotation) — never the tenant's own brand "primary" color.
        Karunia Abadi's brand color happens to BE red, so an Approve/
        Accept button colored "primary" was visually indistinguishable
        from the Reject/Cancel buttons right beside it, despite meaning
        the opposite thing.
    --}}
    @if ($quotation)
        <div class="flex flex-wrap items-center gap-2">
            @if ($quotation->status === \App\Enums\QuotationStatus::Draft)
                <x-button text="Approve" icon="check-circle" color="green" sm wire:click="approve" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Approved)
                <x-button text="Send" icon="paper-airplane" color="blue" sm wire:click="send" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Sent)
                <x-button text="Accept" icon="check" color="green" sm wire:click="openAcceptModal" />
                <x-button text="Reject" icon="x-mark" color="red" sm wire:click="reject" wire:confirm="Reject this quotation?" />
                <x-button text="Mark expired" icon="clock" color="gray" sm wire:click="markExpired" wire:confirm="Mark this quotation expired?" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Accepted && ! $quotation->salesOrder()->exists())
                <x-button text="Create job" icon="briefcase" color="green" sm wire:click="createJob" wire:confirm="Create a job from this quotation?" />
            @endif
            @if (! $quotation->status->isTerminal())
                {{-- icon="document-minus" — closest available Heroicon to a
                     "broken/voided paper" glyph; this set has no literal one. --}}
                <x-button text="Cancel quotation" icon="document-minus" color="red" sm wire:click="cancel" wire:confirm="Cancel this quotation?" />
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
        <div class="flex flex-col gap-4">
            {{-- Client & terms --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Client &amp; quotation terms</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-select.styled wire:model.live="client_id" label="Client" searchable required
                            :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
                    </div>
                    <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                    <x-select.styled wire:model="pricing_mode" label="Pricing mode" required
                        :options="collect($pricingModes)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
                    <x-select.styled wire:model="job_type" label="Job type" required
                        :options="collect($jobTypes)->map(fn ($t) => ['label' => $t->getLabel(), 'value' => $t->value])->all()" />
                    <x-date wire:model="quotation_date" label="Quotation date" />
                    <x-date wire:model="valid_until" label="Valid until" />
                    <x-input wire:model="discount" label="Discount" type="number" step="0.01" />
                    <div class="flex items-end pb-2">
                        <x-toggle wire:model="discount_is_percentage" label="Discount is a percentage" />
                    </div>
                </div>
            </x-card>

            {{-- Line items --}}
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Line items</span>
                        @if ($quotation)
                            <x-button text="Add line item" icon="plus" color="blue" sm wire:click="addItem" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $quotation)
                    <p class="text-sm text-gray-400">Save the quotation first to add line items.</p>
                @else
                    <x-table :headers="[
                        ['index' => 'image', 'label' => '', 'sortable' => false],
                        ['index' => 'title', 'label' => 'Item', 'sortable' => false],
                        ['index' => 'quantity', 'label' => 'Qty', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'unit_cost', 'label' => 'Unit cost', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'line_total', 'label' => 'Line total', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'actions', 'label' => '', 'sortable' => false],
                    ]" :rows="$items">
                        @interact('column_image', $row)
                            @if ($row['image'])
                                <img src="{{ $row['image'] }}" alt="" class="w-8 h-8 rounded object-cover">
                            @endif
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
                    <x-textarea wire:model="terms" label="Terms" rows="4" />
                    <x-textarea wire:model="notes" label="Notes" rows="4" />
                </div>
            </x-card>
        </div>

        {{-- Financial summary + customer PO / COC --}}
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
                        <span class="tabular-nums">{{ $discount_is_percentage ? $discount.'%' : \App\Filament\Support\Money::format($discount, $currency) }}</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-gray-900 dark:text-gray-100">Total</span>
                        <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Recomputed automatically from the line items above. Tax is applied once this quotation becomes an invoice.</p>
                </div>
            </x-card>

            @if ($quotation && $quotation->customer_po_number)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Customer PO / COC</span>
                    </x-slot:header>
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Number</span>
                            <span class="font-semibold">{{ $quotation->customer_po_number }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Date</span>
                            <span>{{ $quotation->customer_po_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                        @if ($quotation->customer_po_is_system_generated)
                            <x-badge text="System-generated Customer Order Confirmation" color="amber" sm />
                        @endif
                    </div>
                </x-card>
            @endif

            @if ($quotation?->salesOrder)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job</span>
                    </x-slot:header>
                    <p class="text-sm">{{ $quotation->salesOrder->number }}</p>
                </x-card>
            @endif
        </div>
    </div>

    {{-- Line item modal --}}
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
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showItemModal', false)" />
            <x-button text="Save line item" color="blue" wire:click="saveItem" />
        </x-slot:footer>
    </x-modal>

    {{-- Accept modal — same as TallStackQuotations. --}}
    <x-modal wire="showAcceptModal" title="Accept quotation" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="customerPoNumber" label="Customer PO number" hint="Leave blank to generate an internal Customer Order Confirmation instead." />
            <x-date wire:model="customerPoDate" label="Customer PO date" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAcceptModal', false)" />
            <x-button text="Accept" color="green" wire:click="accept" />
        </x-slot:footer>
    </x-modal>
</div>
