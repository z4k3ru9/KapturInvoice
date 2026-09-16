<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.vendor-purchase-orders', $company)],
            ['label' => 'Vendor purchase orders', 'url' => route('tallstack.vendor-purchase-orders', $company)],
            ['label' => $purchaseOrder ? $purchaseOrder->number : 'New'],
        ]"
        :title="$purchaseOrder ? $purchaseOrder->number : 'New vendor purchase order'"
    >
        @if ($purchaseOrder)
            <x-slot:badge>
                <x-badge text="{{ $purchaseOrder->status->getLabel() }}" :color="$statusColor" sm />
            </x-slot:badge>
        @endif
        <x-slot:actions>
            @if ($purchaseOrder && $purchaseOrder->status === \App\Enums\VendorPurchaseOrderStatus::Draft)
                {{-- Draft-only autosave status for the Terms/Notes tabs
                     below — see App\Livewire\Concerns\AutosavesDraft. --}}
                <x-tallstack.autosave-status :status="$autosaveStatus" :error="$autosaveError" :conflict-fields="$autosaveConflictFields" />
            @endif
            @if ($purchaseOrder)
                <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('vendor-purchase-orders.pdf', $purchaseOrder) }}" target="_blank" color="gray" sm class="h-9" />
            @endif
            <x-button text="Save" icon="document-check" color="blue" sm class="h-9" wire:click="save" loading="save" spinner="dots" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Status action bar — every transition calls the exact same
         App\Actions\Procurement\* class the Filament table row action uses;
         `status` is never set directly from this UI. The PO's own `total`
         stays immutable once approved — an over-budget bill is handled
         entirely by the variance mechanism below, never by re-editing this
         total. --}}
    @if ($purchaseOrder)
        <div class="flex flex-wrap items-center gap-2">
            @if ($purchaseOrder->status === \App\Enums\VendorPurchaseOrderStatus::Draft)
                <x-button text="Approve" icon="check-circle" color="green" sm wire:click="approve" wire:confirm="Approve this vendor purchase order? Its total becomes immutable." loading="approve" spinner="dots" />
            @endif
            @if ($this->canApproveVariance)
                <x-button text="Record variance" icon="arrows-right-left" color="amber" sm wire:click="openVarianceModal" />
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
        <div class="flex flex-col gap-4">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Vendor purchase order</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-select.styled wire:model="vendor_id" label="Vendor" searchable required
                            :options="$vendors->map(fn ($v) => ['label' => $v->name, 'value' => (string) $v->id])->all()" />
                    </div>
                    {{--
                        Number field: hidden entirely on create — the number
                        is auto-assigned silently by
                        App\Services\DocumentNumberGenerator at save time
                        (Phase 11, repair plan). Kept visible and editable on
                        edit, since a manually typed number on an
                        already-saved document must stay correctable.
                    --}}
                    @if ($purchaseOrder)
                        <x-input wire:model="number" label="Number" hint="Leave blank to auto-assign from the company numbering sequence." />
                    @endif
                    <x-date wire:model="po_date" label="PO date" />
                    <x-date wire:model="due_date" label="Due date" />
                    <x-date wire:model="delivery_date" label="Delivery date" />
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Line items</span>
                        @if ($purchaseOrder && $purchaseOrder->status === \App\Enums\VendorPurchaseOrderStatus::Draft)
                            <x-button text="Add line item" icon="plus" color="blue" sm wire:click="addItem" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $purchaseOrder)
                    <p class="text-sm text-gray-400">Save the purchase order first to add line items.</p>
                @else
                    @php $itemsAreReorderable = $purchaseOrder->status === \App\Enums\VendorPurchaseOrderStatus::Draft; @endphp
                    <x-tallstack.reorderable-items-table :reorderable="$itemsAreReorderable" reorder-method="reorderItems">
                        <x-slot:head>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit cost</th>
                            <th class="px-3 py-2 text-right">Discount</th>
                            <th class="px-3 py-2 text-right">Line total</th>
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
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['unit_cost'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['discount'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['line_total'] }}</td>
                                <td class="px-3 py-2">
                                    {{-- All of this row's actions on one
                                         side — the reorder handle used to
                                         be its own leading column. Edit +
                                         Delete are a real <x-button.group>. --}}
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($purchaseOrder->status === \App\Enums\VendorPurchaseOrderStatus::Draft)
                                            <x-button.group>
                                                <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editItem({{ $row['id'] }})" />
                                                <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteItem({{ $row['id'] }})" wire:confirm="Remove this line item?" />
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
                @endif
            </x-card>

            @if ($purchaseOrder)
                <x-card>
                    <x-slot:header>
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Variances</span>
                    </x-slot:header>
                    @if ($variances->isEmpty())
                        <p class="text-sm text-gray-400">No variances recorded. The payment ceiling equals this PO's total.</p>
                    @else
                        <x-table :headers="[
                            ['index' => 'reason', 'label' => 'Reason', 'sortable' => false],
                            ['index' => 'amount', 'label' => 'Amount', 'sortable' => false, 'align' => 'right'],
                            ['index' => 'approved_by', 'label' => 'Approved by', 'sortable' => false],
                            ['index' => 'approved_at', 'label' => 'Approved at', 'sortable' => false],
                        ]" :rows="$variances">
                            <x-slot:empty>No variances recorded.</x-slot:empty>
                        </x-table>
                    @endif
                </x-card>
            @endif

            <x-card>
                {{-- Static text-only tab labels, no reactive right-slot badge — see
                     tallstack-client-detail.blade.php's own warning: TallStackUI
                     4.1's <x-tab.items> bakes a slot:right badge into a one-time
                     Alpine x-init, so a Livewire re-render duplicates stale tab
                     headers if one is used there. --}}
                <x-tab selected="terms" scroll-on-mobile>
                    <x-tab.items tab="terms" title="Terms">
                        {{-- Livewire's .live/.debounce modifiers on wire:model are not honored by
                             <x-editor> — see TallStackInvoiceForm's own Terms card comment for the
                             full explanation of this $wire.$commit() pattern. --}}
                        <x-editor wire:model="terms" label="Terms" min-height="8rem" max-height="20rem"
                            :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                            x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                    </x-tab.items>
                    <x-tab.items tab="notes" title="Notes">
                        <x-editor wire:model="notes" label="Notes" min-height="8rem" max-height="20rem"
                            :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']"
                            x-on:editor:change.debounce.1750ms="$wire.$commit()" />
                    </x-tab.items>
                </x-tab>
            </x-card>
        </div>

        <div class="flex flex-col gap-4">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Financial summary</span>
                </x-slot:header>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-gray-900 dark:text-gray-100!">Total (immutable once approved)</span>
                        <span class="font-bold text-lg tabular-nums">{{ $total }}</span>
                    </div>
                    @if ($paymentCeiling)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400!">Payment ceiling</span>
                            <span class="font-semibold tabular-nums">{{ $paymentCeiling }}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Total plus every approved variance — the amount vendor bill payments against this PO may not exceed.</p>
                    @endif
                </div>
            </x-card>
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

    {{-- Variance modal — Owner/Admin only, re-checked server-side by
         App\Actions\Procurement\ApproveVendorPoVariance regardless of this
         UI-level gate. --}}
    <x-modal wire="showVarianceModal" title="Record vendor PO variance" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400!">
                Raises the payment ceiling without ever changing this PO's own immutable total.
            </p>
            <x-textarea wire:model="variance_reason" label="Reason" required rows="3" />
            <x-input wire:model="variance_amount" label="Amount" type="number" step="0.01" required hint="Must be positive — raises the payment ceiling." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showVarianceModal', false)" />
            <x-button text="Approve variance" color="amber" wire:click="recordVariance" loading="recordVariance" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
