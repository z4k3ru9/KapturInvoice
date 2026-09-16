<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.vendor-bills', $company)],
            ['label' => 'Vendor bills', 'url' => route('tallstack.vendor-bills', $company)],
            ['label' => $bill ? $bill->number : 'New'],
        ]"
        :title="$bill ? $bill->number : 'New vendor bill'"
    >
        @if ($bill)
            <x-slot:badge>
                <x-badge text="{{ $bill->status->getLabel() }}" :color="$statusColor" sm />
            </x-slot:badge>
        @endif
        <x-slot:actions>
            @if ($bill && $bill->status === \App\Enums\VendorBillStatus::Draft)
                {{-- Draft-only autosave status for the Notes field below —
                     see App\Livewire\Concerns\AutosavesDraft. --}}
                <x-tallstack.autosave-status :status="$autosaveStatus" :error="$autosaveError" :conflict-fields="$autosaveConflictFields" />
            @endif
            @if ($bill)
                <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('vendor-bills.pdf', $bill) }}" target="_blank" color="gray" sm class="h-9" />
            @endif
            <x-button text="Save" icon="document-check" color="blue" sm class="h-9" wire:click="save" loading="save" spinner="dots" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Status action bar — every transition/payment action calls the exact
         same App\Actions\Procurement\* class the Filament resource's row/
         relation-manager actions use; `status` is never set directly here. --}}
    @if ($bill)
        <div class="flex flex-wrap items-center gap-2">
            @if ($bill->status === \App\Enums\VendorBillStatus::Draft)
                <x-button text="Submit" icon="paper-airplane" color="blue" sm wire:click="submit" loading="submit" spinner="dots" />
            @endif
            @if ($bill->status === \App\Enums\VendorBillStatus::Submitted)
                <x-button text="Approve" icon="check-circle" color="green" sm wire:click="approve" loading="approve" spinner="dots" />
            @endif
            @if (in_array($bill->status, [\App\Enums\VendorBillStatus::Approved, \App\Enums\VendorBillStatus::PartiallyPaid]))
                <x-button text="Record payment" icon="banknotes" color="green" sm wire:click="openPaymentModal" />
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
        <div class="flex flex-col gap-4">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Vendor bill</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-select.styled wire:model.live="vendor_id" label="Vendor" searchable required
                        :options="$vendors->map(fn ($v) => ['label' => $v->name, 'value' => (string) $v->id])->all()" />
                    <x-select.styled wire:model="vendor_purchase_order_id" label="Vendor purchase order" searchable required
                        :options="$this->vendorPurchaseOrderOptions->map(fn ($po) => ['label' => $po->number, 'value' => (string) $po->id])->all()" />
                    {{--
                        Number field: hidden entirely on create — the number
                        is auto-assigned silently by
                        App\Services\DocumentNumberGenerator at save time
                        (Phase 11, repair plan). Kept visible and editable on
                        edit, since a manually typed number on an
                        already-saved document must stay correctable.
                    --}}
                    @if ($bill)
                        <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                    @endif
                    <x-date wire:model="bill_date" label="Bill date" />
                    <x-date wire:model="due_date" label="Due date" />
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Line items</span>
                        @if ($bill && $bill->status === \App\Enums\VendorBillStatus::Draft)
                            <x-button text="Add line item" icon="plus" color="blue" sm wire:click="addItem" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $bill)
                    <p class="text-sm text-gray-400">Save the vendor bill first to add line items.</p>
                @else
                    @php $itemsAreReorderable = $bill->status === \App\Enums\VendorBillStatus::Draft; @endphp
                    <x-tallstack.reorderable-items-table :reorderable="$itemsAreReorderable" reorder-method="reorderItems">
                        <x-slot:head>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Discount</th>
                            <th class="px-3 py-2 text-right">Net</th>
                            <th class="px-3 py-2 text-right">Tax</th>
                            <th class="px-3 py-2 text-right">Gross</th>
                            <th class="px-3 py-2 text-right">Unallocated</th>
                            <th class="px-3 py-2"></th>
                        </x-slot:head>

                        @forelse ($items as $index => $row)
                            <x-tallstack.reorderable-item-row :id="$row['id']" :reorderable="$itemsAreReorderable" :first="$loop->first" :last="$loop->last">
                                <td class="px-3 py-2">
                                    @if ($row['image'])
                                        <div class="w-8 h-8 shrink-0">
                                            <img src="{{ $row['image'] }}" alt="" class="w-8 h-8 rounded object-cover">
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-left">{{ $row['title'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['quantity'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['discount'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['net_amount'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['tax_amount'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['line_total'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    <span class="{{ $row['unallocated_raw'] > 0.009 ? 'text-amber-600 dark:text-amber-400! font-semibold' : 'text-gray-400' }}">
                                        {{ $row['unallocated'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    {{-- All of this row's actions on one
                                         side — the reorder handle used to
                                         be its own leading column. Edit +
                                         Delete are a real <x-button.group>;
                                         "Allocate to job" stays its own
                                         separate button since it's not an
                                         edit action. --}}
                                    <div class="flex items-center justify-end gap-3">
                                        <x-button icon="arrows-right-left" sm color="blue" scope="icon-action" class="h-9 w-9" wire:click="openAllocateModal({{ $row['id'] }})" tooltip="Allocate to job" aria-label="Allocate to job" />
                                        @if ($bill->status === \App\Enums\VendorBillStatus::Draft)
                                            <x-button.group>
                                                <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editItem({{ $row['id'] }})" aria-label="Edit line item" />
                                                <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteItem({{ $row['id'] }})" wire:confirm="Remove this line item?" aria-label="Delete line item" />
                                            </x-button.group>
                                        @endif
                                        @if ($itemsAreReorderable)
                                            <x-tallstack.reorder-handle />
                                        @endif
                                    </div>
                                </td>
                            </x-tallstack.reorderable-item-row>
                        @empty
                            <tr>
                                <td colspan="100%" class="px-3 py-6 text-center text-sm text-gray-400">No line items yet.</td>
                            </tr>
                        @endforelse
                    </x-tallstack.reorderable-items-table>

                    {{-- Per-line job-cost allocation breakdown — "one vendor
                         purchase may serve multiple jobs; show unallocated
                         remainder and exclude it from confidently allocated
                         margin." --}}
                    @foreach ($items as $row)
                        @if ($row['allocations']->isNotEmpty())
                            <div class="mt-2 px-2 pb-2 flex flex-wrap items-center gap-1.5">
                                <span class="text-[11px] text-gray-400">{{ $row['title'] }} allocated to:</span>
                                @foreach ($row['allocations'] as $allocation)
                                    <x-badge text="{{ $allocation['job'] }}: {{ $allocation['amount'] }}" color="blue" sm />
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                @endif
            </x-card>

            {{-- Payments — "vendor bills support partial vendor payments and
                 evidence"; every action here calls the exact same
                 App\Actions\Procurement\* class the read-only Filament
                 PaymentsRelationManager's own custom actions use. --}}
            @if ($bill)
                @php
                    $canVerifyPayments = $this->canVerifyPayments;
                @endphp
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Vendor payments</span>
                    </x-slot:header>

                    <x-table :headers="[
                        ['index' => 'number', 'label' => 'Receipt #', 'sortable' => false],
                        ['index' => 'amount', 'label' => 'Amount', 'sortable' => false, 'align' => 'right'],
                        ['index' => 'payment_date', 'label' => 'Date', 'sortable' => false],
                        ['index' => 'method', 'label' => 'Method', 'sortable' => false],
                        ['index' => 'status', 'label' => 'Status', 'sortable' => false],
                        ['index' => 'actions', 'label' => '', 'sortable' => false],
                    ]" :rows="$payments">
                        @interact('column_status', $row)
                            <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                        @endinteract
                        @interact('column_actions', $row, $canVerifyPayments)
                            @php
                                $extraActions = [];
                                if ($row['status'] === \App\Enums\VendorPaymentStatus::Pending && $canVerifyPayments) {
                                    $extraActions[] = ['text' => 'Verify', 'icon' => 'check-badge', 'color' => 'green', 'click' => 'openVerifyModal('.$row['id'].')'];
                                }
                                if ($row['status'] === \App\Enums\VendorPaymentStatus::Verified && ! $row['has_receipt']) {
                                    $extraActions[] = ['text' => 'Issue receipt', 'icon' => 'document-text', 'click' => 'issueReceipt('.$row['id'].')'];
                                }
                                if ($row['has_receipt'] && $row['status'] !== \App\Enums\VendorPaymentStatus::Reversed) {
                                    $extraActions[] = ['text' => 'Amend', 'icon' => 'pencil-square', 'click' => 'openAmendModal('.$row['id'].')'];
                                }
                                if (in_array($row['status'], [\App\Enums\VendorPaymentStatus::Pending, \App\Enums\VendorPaymentStatus::Verified])) {
                                    $extraActions[] = ['text' => 'Reverse', 'icon' => 'arrow-uturn-left', 'color' => 'red', 'click' => 'openReverseModal('.$row['id'].')'];
                                }
                            @endphp
                            <div class="flex items-center justify-end gap-2">
                                @if ($row['has_receipt'])
                                    <x-button icon="document-arrow-down" href="{{ $row['receipt_url'] }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download receipt" />
                                @endif
                                <x-tallstack.row-actions :items="$extraActions" />
                            </div>
                        @endinteract
                        <x-slot:empty>No payments recorded yet.</x-slot:empty>
                    </x-table>
                </x-card>
            @endif

            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Notes</span>
                </x-slot:header>
                {{-- Livewire's .live/.debounce modifiers on wire:model are not honored by
                     <x-editor> — see TallStackInvoiceForm's own Terms card comment for the
                     full explanation of this $wire.$commit() pattern. --}}
                <x-editor wire:model="notes" label="Notes" min-height="8rem" max-height="18rem"
                    :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                    x-on:editor:change.debounce.1750ms="$wire.$commit()" />
            </x-card>
        </div>

        {{-- Financial summary --}}
        <div class="flex flex-col gap-4">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Financial summary</span>
                </x-slot:header>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Total (net + tax)</span>
                        <span class="font-semibold tabular-nums">{{ $total }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Paid</span>
                        <span class="tabular-nums">{{ $amountPaid }}</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-800! my-1"></div>
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-gray-900 dark:text-gray-100!">Balance</span>
                        <span class="font-bold text-lg tabular-nums">{{ $balance }}</span>
                    </div>
                    @if ($paymentCeiling)
                        <p class="text-[11px] text-gray-400 mt-1">Vendor PO payment ceiling: {{ $paymentCeiling }} — payment above it is blocked until an Owner/Admin approves a variance on the PO.</p>
                    @endif
                </div>
            </x-card>

            @if ($bill?->vendorPurchaseOrder)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Vendor purchase order</span>
                    </x-slot:header>
                    <a href="{{ route('tallstack.vendor-purchase-orders.edit', [$company, $bill->vendorPurchaseOrder]) }}" class="text-sm font-semibold text-[color:var(--ts-primary)] hover:underline">
                        {{ $bill->vendorPurchaseOrder->number }}
                    </a>
                </x-card>
            @endif
        </div>
    </div>

    {{-- Line item modal --}}
    <x-modal wire="showItemModal" title="{{ $editingItemId ? 'Edit line item' : 'Add line item' }}" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model.live="item_product_id" label="Product (optional)" searchable clearable
                :options="$products->map(fn ($p) => ['label' => $p->name, 'value' => (string) $p->id])->all()" />
            <x-select.styled wire:model="item_vendor_po_item_id" label="Vendor PO line (optional)" searchable clearable
                :options="$this->vendorPoItemOptions->map(fn ($i) => ['label' => $i->title, 'value' => (string) $i->id])->all()" />
            <x-input wire:model="item_title" label="Title" required />
            <x-textarea wire:model="item_description" label="Description" rows="2" />
            <div class="grid grid-cols-2 gap-4">
                <x-input wire:model.live.debounce.500ms="item_quantity" label="Quantity" type="number" step="0.0001" />
                <x-currency wire:model.live.debounce.500ms="item_unit_cost" label="Unit cost" locale="id-ID" :decimals="2" :precision="4" decimal />
                <x-input wire:model="item_discount" label="Discount" type="number" step="0.01" />
                <div class="flex items-end pb-2 col-span-2">
                    <button type="button" wire:click="$toggle('item_discount_is_percentage')"
                        class="inline-flex items-center gap-1 text-xs font-medium transition-colors {{ $item_discount_is_percentage ? 'text-[color:var(--ts-primary)]' : 'text-gray-400 dark:text-gray-500! hover:text-gray-600 dark:hover:text-gray-300!' }}">
                        @if ($item_discount_is_percentage)
                            <x-icon name="check-circle" class="w-3.5 h-3.5" />
                        @endif
                        Discount is a percentage
                    </button>
                </div>
                <x-input wire:model.live.debounce.500ms="item_net_amount" label="Net amount" type="number" step="0.01" hint="Before this line's discount. Pre-filled from qty x unit cost — edit freely if the vendor's actual bill differs." />
                <x-input wire:model="item_tax_amount" label="Tax amount" type="number" step="0.01" />
            </div>
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showItemModal', false)" />
            <x-button text="Save line item" color="blue" wire:click="saveItem" />
        </x-slot:footer>
    </x-modal>

    {{-- Job-cost allocation modal — App\Actions\Procurement\AllocateJobCost,
         guarded server-side by VendorBillItem::unallocatedAmount() so a
         source line is never over-allocated. Calling this more than once
         against the same line for different jobs is the normal "shared
         purchase" case, not an error. --}}
    <x-modal wire="showAllocateModal" title="Allocate to job" center="sm">
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model="allocate_sales_order_id" label="Job" searchable required
                :options="$this->searchSalesOrders()->map(fn ($so) => ['label' => $so->number, 'value' => (string) $so->id])->all()" />
            <x-input wire:model="allocate_amount" label="Amount" type="number" step="0.01" required />
            <x-input wire:model="allocate_quantity" label="Quantity (optional)" type="number" step="0.0001" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAllocateModal', false)" />
            <x-button text="Allocate" color="blue" wire:click="allocate" loading="allocate" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Record payment modal — App\Actions\Procurement\RecordVendorPayment,
         starts VendorPaymentStatus::Pending; re-checks the PO-wide payment
         ceiling server-side regardless of what's typed here. --}}
    <x-modal wire="showPaymentModal" title="Record vendor payment" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="payment_amount" label="Amount" type="number" step="0.01" required />
            <x-date wire:model="payment_date" label="Payment date" />
            <x-select.styled wire:model="payment_method" label="Method" clearable
                :options="collect($paymentMethods)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
            <x-input wire:model="payment_reference" label="Reference" />
            <x-upload wire:model="payment_proof" label="Proof of payment" tip="PDF, JPG or PNG up to 10MB" :preview="false" />
            <x-textarea wire:model="payment_notes" label="Notes" rows="2" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showPaymentModal', false)" />
            <x-button text="Record payment" color="green" wire:click="recordPayment" loading="recordPayment" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Verify payment modal — App\Actions\Procurement\VerifyVendorPayment,
         Owner/Admin/Accountant only (re-checked server-side); a cheque
         requires a cleared date before it can verify. --}}
    <x-modal wire="showVerifyModal" title="Verify vendor payment" center="sm">
        <div class="flex flex-col gap-4">
            <x-date wire:model="verify_cheque_cleared_at" label="Cheque cleared at" hint="Only required for a cheque payment not already marked cleared." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showVerifyModal', false)" />
            <x-button text="Verify" color="green" wire:click="verifyPayment" loading="verifyPayment" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Amend payment modal — App\Actions\Procurement\AmendVendorPayment,
         only legal once a receipt already exists; re-validates the PO-wide
         payment ceiling server-side. --}}
    <x-modal wire="showAmendModal" title="Amend vendor payment" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="amend_amount" label="Amended amount" type="number" step="0.01" required />
            <x-textarea wire:model="amend_reason" label="Reason" required rows="3" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAmendModal', false)" />
            <x-button text="Amend" color="blue" wire:click="amendPayment" loading="amendPayment" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Reverse payment modal — App\Actions\Procurement\ReverseVendorPayment;
         the payment moves to Reversed but is never deleted. --}}
    <x-modal wire="showReverseModal" title="Reverse vendor payment" center="sm">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="reverse_reason" label="Reason" required rows="3" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showReverseModal', false)" />
            <x-button text="Reverse" color="red" wire:click="reversePayment" loading="reversePayment" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
