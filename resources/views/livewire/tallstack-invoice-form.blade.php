<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route($this->isQuote ? 'tallstack.quotes' : 'tallstack.invoices', $company)],
            ['label' => $this->isQuote ? 'Quotes' : 'Invoices', 'url' => route($this->isQuote ? 'tallstack.quotes' : 'tallstack.invoices', $company)],
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
            <x-button text="Save" icon="document-check" color="brand" sm class="h-9" wire:click="save" loading="save" spinner="dots" />
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
            @unless ($this->isQuote)
                @if (in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Approved], true))
                    <x-button text="Issue" icon="check-circle" color="green" sm wire:click="issue" wire:confirm="Issue this invoice? This freezes its totals and assigns a permanent number." loading="issue" spinner="dots" />
                @endif
            @endunless
            <x-button text="{{ $invoice->status === \App\Enums\InvoiceStatus::Draft ? 'Send' : 'Resend' }}" icon="paper-airplane" color="blue" sm wire:click="openSendModal" />
            @unless ($this->isQuote)
                @if ($invoice->status->canTransitionTo(\App\Enums\InvoiceStatus::Amended))
                    <x-button text="Amend" icon="document-duplicate" color="gray" sm wire:click="openCorrectionModal('amend')" />
                @endif
                @if ($invoice->status->canTransitionTo(\App\Enums\InvoiceStatus::Void))
                    <x-button text="Void & reissue" icon="no-symbol" color="red" sm wire:click="openCorrectionModal('void')" />
                @endif
            @endunless
        </div>
    @endif

    {{--
        Phase 4 (docs/rebuild — TallStackUI repair plan) tabbed layout,
        replacing the previous stacked-cards/two-column arrangement. This
        is the first use of TallStackUI's own <x-tab> component anywhere
        in this codebase (see vendor/tallstackui/tallstackui/.ai/components/tab) —
        every other TallStack*Form page still uses the old stacked-cards
        shape; this establishes the pattern later forms can copy. Plain
        client-side ("selected", no wire:model) since every tab's content
        is already present in one render() payload regardless of which
        tab is open — there's nothing server-side to defer.
    --}}
    <x-tab selected="client-terms">
        <x-tab.items tab="client-terms" title="Client & Terms">
            <div class="flex flex-col gap-4">
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Client &amp; invoice terms</span>
                    </x-slot:header>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <x-select.styled wire:model.live="client_id" label="Client" searchable required
                                :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
                        </div>
                        {{--
                            Job link (App\Models\Invoice::sales_order_id) — the
                            manual entry point for when this form is reached
                            directly (Invoices > Create), scoped to the
                            selected client's own open jobs (render()'s
                            $salesOrders). Prefilled instead, with the client
                            locked in alongside it, when reached from the
                            Job's own Billing tab "Create invoice" action —
                            see mount()'s `?sales_order_id=` handling.
                        --}}
                        <div class="sm:col-span-2">
                            <x-select.styled wire:model="sales_order_id" label="Job (optional)" searchable clearable
                                :options="$salesOrders"
                                hint="Links this invoice to a job for cost/margin reporting. Scoped to the selected client's own open jobs." />
                        </div>
                        {{--
                            Number field: hidden entirely on create — the
                            number is auto-assigned silently by
                            App\Services\DocumentNumberGenerator at save
                            time (Phase 11, repair plan). Kept visible and
                            editable on edit, since a manually typed number
                            on an already-saved document must stay
                            correctable ("a manually typed number is
                            respected and doesn't consume the sequence" —
                            CLAUDE.md).
                        --}}
                        @if ($invoice)
                            <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                        @endif
                        <x-select.styled wire:model="pricing_mode" label="Pricing mode" required
                            :options="collect($pricingModes)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
                        <x-input wire:model.live.debounce.1750ms="po_number" label="PO number" />
                        <x-select.styled wire:model="currency_code" label="Currency" searchable
                            :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->all()" />
                        <x-date wire:model="invoice_date" label="Invoice date" />
                        <x-date wire:model="due_date" label="Due date" />
                        @if ($discount_is_percentage)
                            <x-input wire:model="discount" label="Discount" type="number" step="0.01" suffix="%" />
                        @else
                            <x-currency wire:model="discount" label="Discount" locale="id-ID" :decimals="2" :precision="4" decimal />
                        @endif
                        <div class="flex items-end pb-2 sm:col-span-2">
                            <button type="button" wire:click="$toggle('discount_is_percentage')"
                                class="inline-flex items-center gap-1 text-xs font-medium transition-colors {{ $discount_is_percentage ? 'text-[color:var(--ts-primary)]' : 'text-gray-400 dark:text-gray-500! hover:text-gray-600 dark:hover:text-gray-300!' }}">
                                @if ($discount_is_percentage)
                                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                                @endif
                                Discount is a percentage
                            </button>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Terms</span>
                    </x-slot:header>
                    {{-- Livewire's .live/.debounce modifiers on wire:model are not honored by
                         <x-editor> (it only checks for .live/.blur — see TallStackUI\Support\Blade\
                         Wireable::entangle()), so autosave is wired the equivalent way the
                         AutosavesDraft docblock anticipates: a plain deferred wire:model keeps the
                         property entangled locally, and x-on:editor:change carries Alpine's own
                         .debounce modifier to commit it to the server after the same 1750ms pause.
                         $wire.updated{Field}() itself can't be called directly — Livewire refuses a
                         direct call to a lifecycle-hook-named method ("Unable to call lifecycle
                         method... directly") — so this calls $wire.$commit() instead, which pushes
                         the already-entangled value to the server, where Livewire's own dirty-check
                         then fires updated{Field}() automatically (sanitize + autosaveDraft()) exactly
                         as it would for any other property change. --}}
                    <x-editor wire:model="terms" label="Terms" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                        x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                </x-card>
            </div>
        </x-tab.items>

        <x-tab.items tab="line-items" title="Line Items">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Line items</span>
                        @if ($invoice && ! $itemFormOpen)
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
                    {{--
                        Phase 13 repair plan item 1 — inline editing directly
                        in this table, replacing the previous
                        <x-modal wire="showItemModal"> round-trip. A row
                        being edited (App\Livewire\TallStackInvoiceForm::
                        $editingItemId) swaps to a full-width inline form
                        (partials/invoice-item-form.blade.php); a trailing
                        "add row" renders the same partial when
                        $itemFormOpen is true with no $editingItemId. Other
                        rows' Edit/Delete are disabled while any inline form
                        is open, since the item_* fields back only one row
                        at a time.
                    --}}
                    <x-tallstack.reorderable-items-table :reorderable="$itemsAreReorderable" reorder-method="reorderItems">
                        <x-slot:head>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit cost</th>
                            <th class="px-3 py-2 text-left">Taxes</th>
                            <th class="px-3 py-2 text-right">Line total</th>
                            <th class="px-3 py-2"></th>
                        </x-slot:head>

                        @forelse ($items as $index => $row)
                            @if ($itemFormOpen && $editingItemId === $row['id'])
                                <x-tallstack.reorderable-item-row :id="$row['id']" :reorderable="false" :first="$loop->first" :last="$loop->last">
                                    <td colspan="6" class="px-3 py-3">
                                        @include('livewire.partials.invoice-item-form')
                                    </td>
                                </x-tallstack.reorderable-item-row>
                            @else
                                <x-tallstack.reorderable-item-row :id="$row['id']" :reorderable="$itemsAreReorderable" :first="$loop->first" :last="$loop->last">
                                    <td class="px-3 py-2">
                                        @if ($row['image'])
                                            <img src="{{ $row['image'] }}" alt="" class="w-8 h-8 rounded object-cover">
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-left">{{ $row['title'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @if ($itemsAreReorderable)
                                            {{--
                                                Inline quick-edit: commits on
                                                change (blur or Enter), not
                                                per keystroke — no expand-to-
                                                a-form round trip for the
                                                field people adjust most
                                                often. See
                                                TallStackInvoiceForm::
                                                updateItemInline()'s own
                                                docblock.
                                            --}}
                                            <input type="number" step="0.0001" min="0.0001"
                                                   value="{{ $row['quantity'] }}"
                                                   x-on:change="$wire.updateItemInline({{ $row['id'] }}, 'quantity', $event.target.value)"
                                                   class="w-20 h-8 rounded-md border-gray-200 dark:border-gray-700! dark:bg-gray-800! dark:text-gray-100! text-right tabular-nums text-sm focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
                                        @else
                                            {{ $row['quantity'] }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @if ($itemsAreReorderable)
                                            {{--
                                                A plain type="number" cannot show
                                                thousands/decimal grouping. Shown
                                                pre-formatted (id-ID: "." thousands,
                                                "," decimal) instead; the change
                                                handler undoes that formatting
                                                before it reaches updateItemInline(),
                                                and the field re-renders from the
                                                server's own freshly-recalculated
                                                value after the round trip.
                                            --}}
                                            <input type="text" inputmode="decimal"
                                                   value="{{ number_format((float) $row['unit_cost_raw'], 2, ',', '.') }}"
                                                   x-on:change="$wire.updateItemInline({{ $row['id'] }}, 'unit_cost', parseMoneyInput($event.target.value))"
                                                   class="w-28 h-8 rounded-md border-gray-200 dark:border-gray-700! dark:bg-gray-800! dark:text-gray-100! text-right tabular-nums text-sm focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
                                        @else
                                            {{ $row['unit_cost'] }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-left">
                                        {{-- A real value (the tax name(s)
                                             applied) when there is one; a
                                             plain dash when there isn't —
                                             a "No tax" badge read like an
                                             active toggle for an absent
                                             state, not a value. Changing
                                             which taxes apply still only
                                             happens in the full edit form
                                             (the pencil button), not here. --}}
                                        @forelse ($row['taxes'] as $tax)
                                            <x-badge text="{{ $tax }}" color="gray" sm />
                                        @empty
                                            <span class="text-xs text-gray-400">—</span>
                                        @endforelse
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['line_total'] }}</td>
                                    <td class="px-3 py-2">
                                        {{--
                                            All of this row's actions on one
                                            side, not split left/right —
                                            the reorder handle used to be
                                            its own leading column on the
                                            opposite side of the table from
                                            Edit/Delete. Edit+Delete are a
                                            real <x-button.group> (the same
                                            seamless-join pattern already
                                            established for table row
                                            actions elsewhere), not just two
                                            buttons sitting in a flex row.
                                        --}}
                                        <div class="flex items-center justify-end gap-3">
                                            <x-button.group>
                                                <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editItem({{ $row['id'] }})" :disabled="$itemFormOpen" aria-label="Edit line item" />
                                                <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteItem({{ $row['id'] }})" wire:confirm="Remove this line item?" :disabled="$itemFormOpen" aria-label="Delete line item" />
                                            </x-button.group>
                                            @if ($itemsAreReorderable)
                                                <x-tallstack.reorder-handle />
                                            @endif
                                        </div>
                                    </td>
                                </x-tallstack.reorderable-item-row>
                            @endif
                        @empty
                            @unless ($itemFormOpen)
                                <tr>
                                    <td colspan="100%" class="px-3 py-6 text-center text-sm text-gray-400">No line items yet.</td>
                                </tr>
                            @endunless
                        @endforelse

                        @if ($itemFormOpen && ! $editingItemId)
                            <tr wire:key="item-add-row">
                                <td colspan="6" class="px-3 py-3">
                                    @include('livewire.partials.invoice-item-form')
                                </td>
                            </tr>
                        @endif
                    </x-tallstack.reorderable-items-table>
                @endif
            </x-card>
        </x-tab.items>

        <x-tab.items tab="notes" title="Notes">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Notes</span>
                </x-slot:header>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-editor wire:model="public_notes" label="Public notes" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                        x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                    <x-editor wire:model="private_notes" label="Private notes" min-height="8rem" max-height="18rem"
                        :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                        x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                    <div class="sm:col-span-2">
                        <x-editor wire:model="footer" label="Footer" min-height="4rem" max-height="8rem"
                            :toolbar="['bold', 'italic', 'clear-format', 'undo', 'redo']"
                            x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                    </div>
                </div>
            </x-card>
        </x-tab.items>

        <x-tab.items tab="financial-summary" title="Financial Summary">
            <div class="flex flex-col gap-4">
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Financial summary</span>
                    </x-slot:header>
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400!">Subtotal</span>
                            <span class="font-semibold tabular-nums">{{ $subtotal }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400!">Discount</span>
                            <span class="tabular-nums">{{ $discount_is_percentage ? $discount.'%' : \App\Support\Dashboard\Money::format($discount, $currency) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400!">Tax</span>
                            <span class="tabular-nums">{{ $taxTotal }}</span>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-800! my-1"></div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-900 dark:text-gray-100!">Total</span>
                            <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                        </div>
                        @if ($invoice)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400!">Paid</span>
                                <span class="tabular-nums">{{ $amountPaid }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-900 dark:text-gray-100!">Balance</span>
                                <span class="font-semibold tabular-nums">{{ $balance }}</span>
                            </div>
                        @endif
                        <p class="text-[11px] text-gray-400 mt-1">Recomputed automatically from the line items above. Totals and balance are frozen once issued — see App\Actions\Billing\IssueInvoice.</p>
                    </div>
                </x-card>

                @if ($invoice && ($invoice->originalInvoice || $invoice->correction))
                    <x-card>
                        <x-slot:header>
                            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Correction history</span>
                        </x-slot:header>
                        <div class="flex flex-col gap-2 text-sm">
                            @if ($invoice->originalInvoice)
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400!">Corrects</span>
                                    <a class="font-semibold text-blue-600 dark:text-blue-400! hover:underline" href="{{ route('tallstack.invoices.edit', [$company, $invoice->originalInvoice]) }}">{{ $invoice->originalInvoice->number }}</a>
                                </div>
                            @endif
                            @if ($invoice->correction)
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400!">Corrected by</span>
                                    <a class="font-semibold text-blue-600 dark:text-blue-400! hover:underline" href="{{ route('tallstack.invoices.edit', [$company, $invoice->correction]) }}">{{ $invoice->correction->number }}</a>
                                </div>
                            @endif
                            @if ($invoice->void_reason)
                                <p class="text-gray-500 dark:text-gray-400!">Void reason: {{ $invoice->void_reason }}</p>
                            @endif
                            @if ($invoice->correction_reason)
                                <p class="text-gray-500 dark:text-gray-400!">Correction reason: {{ $invoice->correction_reason }}</p>
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
                                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Tax recap (e-Faktur)</span>
                                <x-button icon="document-arrow-down" sm color="gray" scope="icon-action" class="h-9 w-9" href="{{ route('tax-recaps.pdf', $invoice->taxRecap) }}" target="_blank" tooltip="Download PDF" />
                            </div>
                        </x-slot:header>
                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400!">Number</span>
                                <span class="font-semibold">{{ $invoice->taxRecap->number ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400!">Reporting period</span>
                                <span>{{ $invoice->taxRecap->reporting_period }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400!">Status</span>
                                <x-badge text="{{ $taxRecapStatusLabel }}" :color="$taxRecapStatusColor" sm />
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400!">Filing date</span>
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
                            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Job</span>
                        </x-slot:header>
                        <p class="text-sm">{{ $invoice->salesOrder->number }}</p>
                    </x-card>
                @endif

                {{-- Cross-reference to the quotation this invoice's job descended
                     from — App\Models\Invoice::salesOrder()->quotation(), not a
                     direct link on Invoice itself (an invoice only ever knows
                     its own job; the job carries the quotation reference). Same
                     plain-text visual pattern as the "Job" card above. --}}
                @if ($invoice?->salesOrder?->quotation)
                    <x-card>
                        <x-slot:header>
                            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">From quotation</span>
                        </x-slot:header>
                        <p class="text-sm">{{ $invoice->salesOrder->quotation->number }}</p>
                    </x-card>
                @endif
            </div>
        </x-tab.items>

        <x-tab.items tab="documents" title="Documents">
            {{--
                Documents — attach a file to this invoice (PDF/JPG/PNG up
                to 10MB, App\Livewire\Concerns\ManagesDocuments). Download
                reuses the existing documents.download route; delete
                removes the row (same physical-delete precedent as
                App\Livewire\TallStackDocuments — an uploaded attachment,
                not one of CLAUDE.md's "never physically deleted" issued
                document types).
            --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Documents</span>
                </x-slot:header>
                @if (! $invoice)
                    <p class="text-sm text-gray-400">Save the invoice first to attach documents.</p>
                @else
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
                @endif
            </x-card>
        </x-tab.items>
    </x-tab>

    {{-- Send / Resend modal — always calls App\Services\BillingMailer::sendInvoice()
         unmodified, matching InvoicesTable's own "send"/"cc" schema. --}}
    <x-modal wire="showSendModal" title="{{ $invoice && $invoice->status !== \App\Enums\InvoiceStatus::Draft ? ($this->isQuote ? 'Resend quote' : 'Resend invoice') : ($this->isQuote ? 'Send quote' : 'Send invoice') }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="sendCc" label="CC recipients" rows="2" hint="Optional — one email per line or comma-separated, for this send only." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showSendModal', false)" />
            {{-- color="blue" — Info/communicate role, matching the status
                 bar's own "Send"/"Resend" trigger button above. --}}
            <x-button text="Send" color="blue" wire:click="send" loading="send" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{--
        Amend / Void & reissue slide-over — a required reason plus the full
        corrected line-item set, exactly
        InvoicesTable::correctionSchema()/mapItems()'s own shape. Prefilled
        from the current invoice's items so a reviewer only edits what's
        actually changing.

        A <x-slide>, not a <x-modal>, is deliberate here — this is the one
        content-heavy correction flow (a reason field plus a full re-entry
        table of line items) that reads cramped centered and fits a tall
        side panel instead; every other modal in this form stays a
        <x-modal> (see the Send/tax-recap/item modals above/below).

        Wrapped in @unless ($this->isQuote) so a quote never even renders
        this markup (openCorrectionModal() already never sets
        showCorrectionModal true for one — this is belt-and-braces so
        "Amend"/"Void & reissue" text never appears in a quote's rendered
        HTML at all, not just behind a button that never shows).
    --}}
    @unless ($this->isQuote)
    <x-slide id="correction-slide" wire="showCorrectionModal" :title="$correctionAction === 'void' ? 'Void & reissue invoice' : 'Amend invoice'" size="xl">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="correctionReason" label="Reason" required rows="2" />

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-200!">Corrected line items</span>
                    {{-- color="gray" — Neutral role, not this panel's own
                         Primary/Destructive commit button below. --}}
                    <x-button text="Add row" icon="plus" color="gray" sm wire:click="addCorrectionItem" />
                </div>

                @foreach ($correctionItems as $index => $item)
                    <div wire:key="correction-item-{{ $index }}" class="grid grid-cols-12 gap-2 items-start border border-gray-200 dark:border-gray-800! rounded-lg p-3">
                        <div class="col-span-12 sm:col-span-4">
                            <x-input wire:model="correctionItems.{{ $index }}.title" label="Title" required />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <x-input wire:model="correctionItems.{{ $index }}.quantity" label="Qty" type="number" step="0.0001" />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <x-currency wire:model="correctionItems.{{ $index }}.unit_cost" label="Unit cost" locale="id-ID" :decimals="2" :precision="4" decimal />
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            @if ($item['discount_is_percentage'] ?? false)
                                <x-input wire:model="correctionItems.{{ $index }}.discount" label="Discount" type="number" step="0.01" suffix="%" />
                            @else
                                <x-currency wire:model="correctionItems.{{ $index }}.discount" label="Discount" locale="id-ID" :decimals="2" :precision="4" decimal />
                            @endif
                        </div>
                        <div class="col-span-5 sm:col-span-1 flex items-end pb-2">
                            <button type="button" wire:click="$toggle('correctionItems.{{ $index }}.discount_is_percentage')"
                                title="Discount is a percentage"
                                class="inline-flex items-center gap-1 text-xs font-medium transition-colors {{ ($item['discount_is_percentage'] ?? false) ? 'text-[color:var(--ts-primary)]' : 'text-gray-400 dark:text-gray-500! hover:text-gray-600 dark:hover:text-gray-300!' }}">
                                @if ($item['discount_is_percentage'] ?? false)
                                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                                @endif
                                %
                            </button>
                        </div>
                        <div class="col-span-1 flex items-end justify-end pb-1">
                            <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="removeCorrectionItem({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <x-slot:footer between>
            <x-button text="Cancel" color="gray" wire:click="$set('showCorrectionModal', false)" />
            {{-- Destructive (red) when voiding, otherwise Primary (brand)
                 — this panel's own single commit action either ends the
                 document or just corrects it. --}}
            <x-button :text="$correctionAction === 'void' ? 'Void & reissue' : 'Amend'" :color="$correctionAction === 'void' ? 'red' : 'brand'" wire:click="submitCorrection" loading="submitCorrection" spinner="dots" />
        </x-slot:footer>
    </x-slide>
    @endunless

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
