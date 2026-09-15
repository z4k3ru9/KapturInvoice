<div class="w-[90%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.invoices', $company)],
            ['label' => 'Invoices', 'url' => route('tallstack.invoices', $company)],
            ['label' => $invoice ? $invoice->number : 'New'],
        ]"
        :title="$invoice ? $invoice->number : 'New invoice'"
    >
        @if ($invoice)
            <x-slot:badge>
                <x-badge text="{{ $invoice->status->getLabel() }}" :color="$statusColor" sm />
            </x-slot:badge>
        @endif
        <x-slot:actions>
            @if ($invoice && $invoice->status === \App\Enums\InvoiceStatus::Draft)
                {{-- Draft-only autosave status for the header text fields
                     below (Terms/Public notes/Private notes/Footer/PO
                     number) — see App\Livewire\Concerns\AutosavesDraft.
                     Inline and persistent, never a toast, per
                     docs/rebuild/DESIGN.md §6. --}}
                <x-tallstack.autosave-status :status="$autosaveStatus" :error="$autosaveError" :conflict-fields="$autosaveConflictFields" />
            @endif
            @if ($invoice)
                <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('invoices.pdf', $invoice) }}" target="_blank" color="gray" sm class="h-9" />
            @endif
            {{-- color="brand" — Primary role (AppServiceProvider::
                 registerActionColorPalette()'s docblock): the single main
                 commit action of this page. --}}
            <x-button text="Save" icon="document-check" color="brand" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Status action bar — every transition/correction here calls the
        exact same App\Actions\Billing\* class the Filament table row
        actions use; no status is ever set directly from this UI.

        Colors follow the app-wide semantic palette
        (AppServiceProvider::registerActionColorPalette()'s docblock):
        green = Success (Issue — forward/positive), blue = Info/
        communicate (Send/Resend), gray = Neutral (Amend — a passive
        correction), red = Destructive (Void & reissue). Deliberately
        NOT the tenant's own brand "brand" color for any of these four —
        that's reserved for this page's own single Primary action
        ("Save", in the header above) so all five buttons visible on this
        page stay distinguishable at a glance rather than two of them
        landing on the same hue.
    --}}
    @if ($invoice)
        <div class="flex flex-wrap items-center gap-2">
            @if (in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Approved], true))
                <x-button text="Issue" icon="check-circle" color="green" sm wire:click="issue" wire:confirm="Issue this invoice? This freezes its totals and assigns a permanent number." />
            @endif
            <x-button text="{{ $invoice->status === \App\Enums\InvoiceStatus::Draft ? 'Send' : 'Resend' }}" icon="paper-airplane" color="blue" sm wire:click="openSendModal" />
            @if ($invoice->status->canTransitionTo(\App\Enums\InvoiceStatus::Amended))
                <x-button text="Amend" icon="document-duplicate" color="gray" sm wire:click="openCorrectionModal('amend')" />
            @endif
            @if ($invoice->status->canTransitionTo(\App\Enums\InvoiceStatus::Void))
                <x-button text="Void & reissue" icon="no-symbol" color="red" sm wire:click="openCorrectionModal('void')" />
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
        <div class="flex flex-col gap-4">
            {{-- Client & invoice terms --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Client &amp; invoice terms</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-select.styled wire:model.live="client_id" label="Client" searchable required
                            :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
                    </div>
                    <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                    <x-select.styled wire:model="pricing_mode" label="Pricing mode" required
                        :options="collect($pricingModes)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
                    <x-input wire:model.live.debounce.1750ms="po_number" label="PO number" />
                    <x-input wire:model="currency_code" label="Currency code" />
                    <x-date wire:model="invoice_date" label="Invoice date" />
                    <x-date wire:model="due_date" label="Due date" />
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
                        @if ($invoice)
                            {{-- color="gray" — Neutral role: a secondary
                                 structural action, not this page's own
                                 Primary ("Save", above). --}}
                            <x-button text="Add line item" icon="plus" color="gray" sm wire:click="addItem" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $invoice)
                    <p class="text-sm text-gray-400">Save the invoice first to add line items.</p>
                @else
                    @php $itemsAreReorderable = $invoice->status === \App\Enums\InvoiceStatus::Draft; @endphp
                    <x-tallstack.reorderable-items-table :reorderable="$itemsAreReorderable" reorder-method="reorderItems">
                        <x-slot:head>
                            <th class="px-3 py-2"></th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit cost</th>
                            <th class="px-3 py-2 text-left">Taxes</th>
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
                                <td class="px-3 py-2 text-left">
                                    @forelse ($row['taxes'] as $tax)
                                        <x-badge text="{{ $tax }}" color="gray" sm />
                                    @empty
                                        <x-badge text="No tax" color="gray" sm />
                                    @endforelse
                                </td>
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

            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Notes</span>
                </x-slot:header>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-textarea wire:model.live.debounce.1750ms="terms" label="Terms" rows="3" />
                    <x-textarea wire:model.live.debounce.1750ms="public_notes" label="Public notes" rows="3" />
                    <x-textarea wire:model.live.debounce.1750ms="private_notes" label="Private notes" rows="3" />
                    <x-textarea wire:model.live.debounce.1750ms="footer" label="Footer" rows="3" />
                </div>
            </x-card>
        </div>

        {{-- Financial summary + tax recap / correction history --}}
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
                        <span class="font-bold text-gray-900 dark:text-gray-100">Total</span>
                        <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                    </div>
                    @if ($invoice)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Paid</span>
                            <span class="tabular-nums">{{ $amountPaid }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Balance</span>
                            <span class="font-semibold tabular-nums">{{ $balance }}</span>
                        </div>
                    @endif
                    <p class="text-[11px] text-gray-400 mt-1">Recomputed automatically from the line items above. Totals and balance are frozen once issued — see App\Actions\Billing\IssueInvoice.</p>
                </div>
            </x-card>

            @if ($invoice && ($invoice->originalInvoice || $invoice->correction))
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Correction history</span>
                    </x-slot:header>
                    <div class="flex flex-col gap-2 text-sm">
                        @if ($invoice->originalInvoice)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Corrects</span>
                                <a class="font-semibold text-blue-600 hover:underline" href="{{ route('tallstack.invoices.edit', [$company, $invoice->originalInvoice]) }}">{{ $invoice->originalInvoice->number }}</a>
                            </div>
                        @endif
                        @if ($invoice->correction)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Corrected by</span>
                                <a class="font-semibold text-blue-600 hover:underline" href="{{ route('tallstack.invoices.edit', [$company, $invoice->correction]) }}">{{ $invoice->correction->number }}</a>
                            </div>
                        @endif
                        @if ($invoice->void_reason)
                            <p class="text-gray-500 dark:text-gray-400">Void reason: {{ $invoice->void_reason }}</p>
                        @endif
                        @if ($invoice->correction_reason)
                            <p class="text-gray-500 dark:text-gray-400">Correction reason: {{ $invoice->correction_reason }}</p>
                        @endif
                    </div>
                </x-card>
            @endif

            {{--
                e-Faktur / Tax Recap issuance — only exists once IssueInvoice
                itself decided this invoice was taxable (App\Models\
                Company\CompanyTaxSetting::tax_enabled plus a nonzero tax
                total; Karunia Abadi's own tax_enabled=false never creates
                one). Every field/action here mirrors
                InvoiceInfolist::fileOrAdjustTaxRecapAction() exactly.
            --}}
            @if ($invoice?->taxRecap)
                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between w-full">
                            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Tax recap (e-Faktur)</span>
                            <x-button icon="document-arrow-down" sm color="gray" scope="icon-action" class="h-9 w-9" href="{{ route('tax-recaps.pdf', $invoice->taxRecap) }}" target="_blank" tooltip="Download PDF" />
                        </div>
                    </x-slot:header>
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Number</span>
                            <span class="font-semibold">{{ $invoice->taxRecap->number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Reporting period</span>
                            <span>{{ $invoice->taxRecap->reporting_period }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <x-badge text="{{ ucfirst($invoice->taxRecap->manual_entry_status ?? 'pending') }}" :color="$invoice->taxRecap->manual_entry_status === 'filed' ? 'green' : 'amber'" sm />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Filing date</span>
                            <span>{{ $invoice->taxRecap->filing_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                        {{-- color="brand" — this card's own single commit action (Primary role). --}}
                        <x-button text="{{ $taxRecapAlreadyFiled ? 'Adjust filing' : 'File' }}" icon="document-check" color="brand" sm class="mt-1" wire:click="openTaxRecapModal" />
                    </div>
                </x-card>
            @endif

            @if ($invoice?->salesOrder)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job</span>
                    </x-slot:header>
                    <p class="text-sm">{{ $invoice->salesOrder->number }}</p>
                </x-card>
            @endif

            {{--
                Documents — attach a file to this invoice (PDF/JPG/PNG up
                to 10MB, App\Livewire\Concerns\ManagesDocuments). Download
                reuses the existing documents.download route; delete
                removes the row (same physical-delete precedent as
                App\Livewire\TallStackDocuments — an uploaded attachment,
                not one of CLAUDE.md's "never physically deleted" issued
                document types).
            --}}
            @if ($invoice)
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
            <x-select.styled wire:model="item_tax_rate_ids" label="Taxes" :multiple="true" searchable
                :options="$taxRates->map(fn ($t) => ['label' => $t->name, 'value' => (string) $t->id])->all()" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showItemModal', false)" />
            {{-- color="brand" — this modal's own single commit action (Primary role). --}}
            <x-button text="Save line item" color="brand" wire:click="saveItem" />
        </x-slot:footer>
    </x-modal>

    {{-- Send / Resend modal — always calls App\Services\BillingMailer::sendInvoice()
         unmodified, matching InvoicesTable's own "send"/"cc" schema. --}}
    <x-modal wire="showSendModal" title="{{ $invoice && $invoice->status !== \App\Enums\InvoiceStatus::Draft ? 'Resend invoice' : 'Send invoice' }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="sendCc" label="CC recipients" rows="2" hint="Optional — one email per line or comma-separated, for this send only." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showSendModal', false)" />
            {{-- color="blue" — Info/communicate role, matching the status
                 bar's own "Send"/"Resend" trigger button above. --}}
            <x-button text="Send" color="blue" wire:click="send" />
        </x-slot:footer>
    </x-modal>

    {{--
        Amend / Void & reissue modal — a required reason plus the full
        corrected line-item set, exactly
        InvoicesTable::correctionSchema()/mapItems()'s own shape. Prefilled
        from the current invoice's items so a reviewer only edits what's
        actually changing.
    --}}
    <x-modal wire="showCorrectionModal" :title="$correctionAction === 'void' ? 'Void & reissue invoice' : 'Amend invoice'" size="lg" scrollable>
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="correctionReason" label="Reason" required rows="2" />

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">Corrected line items</span>
                    {{-- color="gray" — Neutral role, not this modal's own
                         Primary/Destructive commit button below. --}}
                    <x-button text="Add row" icon="plus" color="gray" sm wire:click="addCorrectionItem" />
                </div>

                @foreach ($correctionItems as $index => $item)
                    <div wire:key="correction-item-{{ $index }}" class="grid grid-cols-12 gap-2 items-start border border-gray-200 dark:border-gray-800 rounded-lg p-3">
                        <div class="col-span-12 sm:col-span-4">
                            <x-input wire:model="correctionItems.{{ $index }}.title" label="Title" required />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <x-input wire:model="correctionItems.{{ $index }}.quantity" label="Qty" type="number" step="0.0001" />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <x-input wire:model="correctionItems.{{ $index }}.unit_cost" label="Unit cost" type="number" step="0.01" />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <x-input wire:model="correctionItems.{{ $index }}.discount" label="Discount" type="number" step="0.01" />
                        </div>
                        <div class="col-span-5 sm:col-span-1 flex items-end pb-2">
                            <x-toggle wire:model="correctionItems.{{ $index }}.discount_is_percentage" label="%" />
                        </div>
                        <div class="col-span-1 flex items-end justify-end pb-1">
                            <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="removeCorrectionItem({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showCorrectionModal', false)" />
            {{-- Destructive (red) when voiding, otherwise Primary (brand)
                 — this modal's own single commit action either ends the
                 document or just corrects it. --}}
            <x-button text="{{ $correctionAction === 'void' ? 'Void & reissue' : 'Amend' }}" :color="$correctionAction === 'void' ? 'red' : 'brand'" wire:click="submitCorrection" />
        </x-slot:footer>
    </x-modal>

    {{-- Tax recap file/adjust modal — mirrors InvoiceInfolist::fileOrAdjustTaxRecapAction(). --}}
    <x-modal wire="showTaxRecapModal" title="{{ $taxRecapAlreadyFiled ? 'Adjust tax recap filing' : 'File tax recap' }}" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="taxRecapExternalReference" label="External tax-system reference" />
            <x-select.styled wire:model="taxRecapManualEntryStatus" label="Status" required
                :options="[['label' => 'Pending', 'value' => 'pending'], ['label' => 'Filed', 'value' => 'filed']]" />
            <x-date wire:model="taxRecapFilingDate" label="Filing date" />
            <x-input wire:model="taxRecapAttachmentReference" label="Attachment reference" />
            <x-textarea wire:model="taxRecapNotes" label="Notes" rows="2" />
            @if ($taxRecapAlreadyFiled)
                <x-textarea wire:model="taxRecapAdjustmentReason" label="Reason for this adjustment" required rows="2" />
            @endif
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showTaxRecapModal', false)" />
            {{-- color="brand" — this modal's own single commit action (Primary role). --}}
            <x-button text="Save" color="brand" wire:click="submitTaxRecap" />
        </x-slot:footer>
    </x-modal>
</div>
