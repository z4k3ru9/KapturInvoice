<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

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
            {{--
                Compact at-a-glance summary — client/date/number/discount
                shown as plain text beside the status badge, so collapsing
                the "Client & quotation terms" card below (minimize="mount")
                never hides the essentials the user came here to confirm.
            --}}
            <x-slot:meta>
                <span><span class="text-gray-400 dark:text-gray-500">Client</span> {{ $quotation->client?->name ?? '—' }}</span>
                <span><span class="text-gray-400 dark:text-gray-500">Date</span> {{ $quotation->quotation_date?->format('d M Y') ?? '—' }}</span>
                <span><span class="text-gray-400 dark:text-gray-500">Valid until</span> {{ $quotation->valid_until?->format('d M Y') ?? '—' }}</span>
                <span><span class="text-gray-400 dark:text-gray-500">Total</span> <span class="font-semibold text-gray-700 dark:text-gray-300 tabular-nums">{{ $total }}</span></span>
            </x-slot:meta>
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
            <x-button text="Save" icon="document-check" color="blue" sm class="h-9" wire:click="save" loading="save" spinner="dots" />
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
                <x-button text="Approve" icon="check-circle" color="green" sm wire:click="approve" loading="approve" spinner="dots" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Approved)
                <x-button text="Send" icon="paper-airplane" color="blue" sm wire:click="send" loading="send" spinner="dots" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Sent)
                <x-button text="Accept" icon="check" color="green" sm wire:click="openAcceptModal" />
                <x-button text="Reject" icon="x-mark" color="red" sm wire:click="reject" wire:confirm="Reject this quotation?" loading="reject" spinner="dots" />
                <x-button text="Mark expired" icon="clock" color="gray" sm wire:click="markExpired" wire:confirm="Mark this quotation expired?" loading="markExpired" spinner="dots" />
            @endif
            @if ($quotation->status === \App\Enums\QuotationStatus::Accepted && ! $quotation->salesOrder()->exists())
                <x-button text="Create job" icon="briefcase" color="green" sm wire:click="createJob" wire:confirm="Create a job from this quotation?" loading="createJob" spinner="dots" />
            @endif
            @if (! $quotation->status->isTerminal())
                {{-- icon="document-minus" — closest available Heroicon to a
                     "broken/voided paper" glyph; this set has no literal one. --}}
                <x-button text="Cancel quotation" icon="document-minus" color="red" sm wire:click="cancel" wire:confirm="Cancel this quotation?" loading="cancel" spinner="dots" />
            @endif
        </div>
    @endif

    {{--
        Tabbed layout (TallStackUI's <x-tab>/<x-tab.items>, matching
        https://tallstackui.com/docs/ui/tab) — replaces the earlier
        stacked-cards page. Client & Terms / Line Items / Customer PO-COC /
        Documents, each a full-width panel so line items never fight a
        narrow sidebar column. `selected="client"` is a plain initial
        value (no wire:model) — Alpine owns which tab is open client-side,
        matching every panel already being rendered server-side (just
        hidden via x-show), so no extra Livewire round trip is spent on
        switching tabs.
    --}}
    <x-tab selected="client" scroll-on-mobile bordered>
        {{-- ===================== CLIENT & TERMS ===================== --}}
        <x-tab.items tab="client" title="Client & Terms">
            <div class="flex flex-col gap-4">
                <div class="grid lg:grid-cols-2 gap-4 items-start">
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

                    {{-- Financial summary --}}
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
                            <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900 dark:text-gray-100">Total</span>
                                <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Recomputed automatically from the line items above. Tax is applied once this quotation becomes an invoice.</p>
                        </div>
                    </x-card>
                </div>

                {{--
                    Terms & Notes — two distinct rich-text fields (Terms is
                    printed on the quotation PDF; Notes is internal-only),
                    kept as a compact side-by-side stack rather than two
                    separate top-level tabs. Previously both editors sat
                    under a header that only said "Notes", which mislabeled
                    the Terms editor next to it.
                --}}
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Terms &amp; notes</span>
                    </x-slot:header>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-editor wire:model="terms" label="Terms" min-height="8rem" max-height="20rem"
                            :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
                        <x-editor wire:model="notes" label="Notes" min-height="8rem" max-height="20rem"
                            :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
                    </div>
                </x-card>
            </div>
        </x-tab.items>

        {{-- ===================== LINE ITEMS ===================== --}}
        <x-tab.items tab="items" title="Line Items">
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
                    @php $itemsAreReorderable = $quotation->status === \App\Enums\QuotationStatus::Draft; @endphp
                    <x-tallstack.reorderable-items-table :reorderable="$itemsAreReorderable" reorder-method="reorderItems">
                        <x-slot:head>
                            <th class="px-3 py-2"></th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit cost</th>
                            <th class="px-3 py-2 text-right">Line total</th>
                            <th class="px-3 py-2"></th>
                        </x-slot:head>

                        @forelse ($items as $index => $row)
                            <x-tallstack.reorderable-item-row :id="$row['id']" :reorderable="$itemsAreReorderable" :first="$loop->first" :last="$loop->last">
                                <td class="px-3 py-2">
                                    @if ($row['image'])
                                        <img src="{{ $row['image'] }}" alt="" class="w-8 h-8 rounded object-cover">
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-left">{{ $row['title'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['quantity'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['unit_cost'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['line_total'] }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editItem({{ $row['id'] }})" />
                                        <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteItem({{ $row['id'] }})" wire:confirm="Remove this line item?" />
                                    </div>
                                </td>
                            </x-tallstack.reorderable-item-row>
                        @empty
                            <tr>
                                <td colspan="100%" class="px-3 py-6 text-center text-sm text-gray-400">No line items yet.</td>
                            </tr>
                        @endforelse
                    </x-tallstack.reorderable-items-table>
                @endif
            </x-card>
        </x-tab.items>

        {{-- ===================== CUSTOMER PO-COC ===================== --}}
        {{--
            Customer PO/COC, the resulting Job, and every invoice billed
            against that Job ("Billed as") — grouped here since they're
            all workflow cross-references produced once this quotation is
            accepted, none of them editable from this page. "Billed as"
            walks Quotation::salesOrder() (HasOne) -> SalesOrder::invoices()
            (HasMany) rather than assuming any direct link on Quotation
            itself — a Job can carry more than one invoice.
        --}}
        <x-tab.items tab="customer-po" title="Customer PO-COC">
            <div class="flex flex-col gap-4">
                @if (($quotation && $quotation->customer_po_number) || $quotation?->salesOrder)
                    <div class="grid lg:grid-cols-2 gap-4 items-start">
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
                @endif

                @if ($billedInvoices->isNotEmpty())
                    <x-card>
                        <x-slot:header>
                            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Billed as</span>
                        </x-slot:header>
                        <x-table :headers="[
                            ['index' => 'number', 'label' => 'Invoice number'],
                            ['index' => 'date', 'label' => 'Date'],
                            ['index' => 'status', 'label' => 'Status'],
                            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
                            ['index' => 'balance', 'label' => 'Balance', 'align' => 'right'],
                        ]" :rows="$billedInvoices">
                            @interact('column_number', $row)
                                <a href="{{ $row['edit_url'] }}" class="font-semibold text-[color:var(--ts-primary)] hover:underline">{{ $row['number'] }}</a>
                            @endinteract
                            @interact('column_status', $row)
                                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                            @endinteract
                        </x-table>
                    </x-card>
                @elseif (! $quotation?->salesOrder)
                    <p class="text-sm text-gray-400">No Customer PO/COC or job yet — these appear once the quotation is accepted.</p>
                @else
                    <p class="text-sm text-gray-400">This job has no invoices billed against it yet.</p>
                @endif
            </div>
        </x-tab.items>

        {{-- ===================== DOCUMENTS ===================== --}}
        {{-- See App\Livewire\Concerns\ManagesDocuments; same shape as the Invoice form's own Documents card. --}}
        <x-tab.items tab="documents" title="Documents">
            @if ($quotation)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Documents</span>
                    </x-slot:header>
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
                </x-card>
            @else
                <p class="text-sm text-gray-400">Save the quotation first to attach documents.</p>
            @endif
        </x-tab.items>
    </x-tab>

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
            <x-button text="Accept" color="green" wire:click="accept" loading="accept" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
