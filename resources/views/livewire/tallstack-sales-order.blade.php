@php
    $job = $salesOrder;
@endphp
<div class="w-[93%] mx-auto py-6 flex flex-col gap-5" x-data="{ tab: @entangle('activeTab') }">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.jobs', $company)],
            ['label' => 'Jobs', 'url' => route('tallstack.jobs', $company)],
            ['label' => $job->number],
        ]"
        :title="$job->number"
    >
        <x-slot:badge>
            <x-badge text="{{ $job->status->getLabel() }}" :color="$statusColor" sm />
        </x-slot:badge>
        <x-slot:actions>
            <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('sales-orders.pdf', $job) }}" target="_blank" color="gray" sm class="h-9" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Status action bar — same fixed semantic palette as
        TallStackQuotationForm's own (see that file's comment): green for
        forward/positive, blue for neutral in-progress, red for
        destructive/terminal, gray for passive. Never the tenant's brand
        "primary" color. Every button calls the exact same
        App\Actions\Sales\*/Delivery\* class SalesOrdersTable's own row
        actions use.
    --}}
    <div class="flex flex-wrap items-center gap-2">
        @if ($job->status === \App\Enums\SalesOrderStatus::Draft)
            <x-button text="Approve" icon="check-circle" color="green" sm wire:click="approve" wire:confirm="Approve this job? Milestones must total the approved job value." />
        @endif
        @if ($allowedNextStates->isNotEmpty())
            <x-button text="Advance status" icon="arrow-right-circle" color="blue" sm wire:click="openAdvanceModal" />
        @endif
        @if (! $job->status->isTerminal())
            <x-button text="Cancel job" icon="no-symbol" color="red" sm wire:click="cancel" wire:confirm="Cancel this job?" />
        @endif
        @if ($job->operational_closed_at === null)
            <x-button text="Close operationally" icon="check-circle" color="green" sm wire:click="closeOperationally" wire:confirm="Close this job operationally?" />
        @else
            <x-badge text="Operationally closed" color="gray" sm />
        @endif
        @if ($job->financial_closed_at === null)
            <x-button text="Close financially" icon="banknotes" color="green" sm wire:click="openCloseFinanciallyModal" />
        @else
            <x-badge text="Financially closed" color="gray" sm />
        @endif
    </div>

    {{-- Top summary strip — matches the Stitch "Job Workspace" mockups'
         header stat row (Client / Approved value / Paid / Outstanding). --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Client" icon="user" color="blue">
            <span class="text-sm font-bold truncate block">{{ $job->client?->name ?? '—' }}</span>
            <x-slot:footer>{{ $job->job_type->getLabel() }} job</x-slot:footer>
        </x-stats>
        <x-stats scope="compact" title="Approved value" icon="currency-dollar" color="green">
            <span class="text-sm font-bold tabular-nums">{{ $overview['approvedValue'] }}</span>
            <x-slot:footer>Current job value</x-slot:footer>
        </x-stats>
        <x-stats scope="compact" title="Paid amount" icon="banknotes" color="blue">
            <span class="text-sm font-bold tabular-nums">{{ $overview['paidAmount'] }}</span>
            <x-slot:footer>Across all invoices</x-slot:footer>
        </x-stats>
        <x-stats scope="compact" title="Outstanding balance" icon="exclamation-triangle" :color="$overview['outstandingBalance'] === $overview['paidAmount'] ? 'gray' : 'amber'">
            <span class="text-sm font-bold tabular-nums">{{ $overview['outstandingBalance'] }}</span>
            <x-slot:footer>Active invoices only</x-slot:footer>
        </x-stats>
    </div>

    {{-- Tab bar — matches the Stitch mockups' Overview/Commercial/Billing/
         Procurement/Delivery/Margin/Activity order. Alpine-driven so the
         active tab persists client-side without a network round trip;
         `activeTab` is entangled above so every wire:click action still
         knows which tab is open (e.g. for a modal launched from a tab). --}}
    <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="flex flex-wrap gap-1 -mb-px overflow-x-auto">
            @foreach ([
                'overview' => ['label' => 'Overview', 'icon' => 'squares-2x2'],
                'commercial' => ['label' => 'Commercial', 'icon' => 'banknotes'],
                'billing' => ['label' => 'Billing', 'icon' => 'document-currency-dollar', 'count' => $invoices->count()],
                'procurement' => ['label' => 'Procurement', 'icon' => 'shopping-cart'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'truck'],
                'margin' => ['label' => 'Margin', 'icon' => 'chart-bar'],
                'activity' => ['label' => 'Activity', 'icon' => 'clock'],
            ] as $key => $meta)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        class="flex items-center gap-1.5 px-3 py-2.5 text-sm font-semibold border-b-2 whitespace-nowrap {{ $activeTab === $key ? 'border-[color:var(--ts-primary)] text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                    <x-icon :name="$meta['icon']" class="w-4 h-4 shrink-0" />
                    {{ $meta['label'] }}
                    @if (! empty($meta['count']))
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300">{{ $meta['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ============================= OVERVIEW ============================= --}}
    <div @if ($activeTab !== 'overview') hidden @endif class="flex flex-col gap-4">
        <div class="grid lg:grid-cols-[1.6fr_1fr] gap-4 items-start">
            <x-card>
                <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job details</span></x-slot:header>
                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><div class="text-gray-400 text-xs">Job number</div><div class="font-semibold">{{ $job->number }}</div></div>
                    <div><div class="text-gray-400 text-xs">Job type</div><div>{{ $job->job_type->getLabel() }}</div></div>
                    <div><div class="text-gray-400 text-xs">Client</div><div>{{ $job->client?->name ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Source quotation</div><div>{{ $job->quotation?->number ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Customer PO / COC</div><div>{{ $job->quotation?->customer_po_number ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">PO type</div><div>{{ $job->quotation?->customer_po_is_system_generated ? 'System-generated COC' : 'Customer-supplied PO' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Requires handover</div><div>{{ $overview['requiresHandover'] ? 'Yes' : 'No (goods-only job)' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Fully delivered</div><div>{{ $overview['isFullyDelivered'] ? 'Yes' : 'No' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Approved at</div><div>{{ $job->approved_at?->format('d M Y H:i') ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Created at</div><div>{{ $job->created_at?->format('d M Y H:i') ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Operational closure</div><div>{{ $job->operational_closed_at?->format('d M Y H:i') ?? '—' }}</div></div>
                    <div><div class="text-gray-400 text-xs">Financial closure</div><div>{{ $job->financial_closed_at?->format('d M Y H:i') ?? '—' }}</div></div>
                    @if ($job->cancelled_at)
                        <div><div class="text-gray-400 text-xs">Cancelled at</div><div>{{ $job->cancelled_at->format('d M Y H:i') }}</div></div>
                    @endif
                </div>
            </x-card>

            <x-card>
                <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Fulfillment</span></x-slot:header>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center justify-between"><span class="text-gray-500 dark:text-gray-400">Delivery orders</span><span class="font-semibold">{{ $deliveryOrders->count() }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500 dark:text-gray-400">Handover reports</span><span class="font-semibold">{{ $handoverReports->count() }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500 dark:text-gray-400">Invoices</span><span class="font-semibold">{{ $invoices->count() }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500 dark:text-gray-400">Variations recorded</span><span class="font-semibold">{{ $variations->count() }}</span></div>
                </div>
            </x-card>
        </div>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job line items</span>
                    <span class="text-xs text-gray-400">Total: {{ $overview['itemsTotal'] }}</span>
                </div>
            </x-slot:header>
            <p class="text-xs text-gray-400 mb-2">Snapshot copied from the accepted quotation — read-only. A scope change goes through a job variation on the Activity tab instead.</p>
            <x-table :headers="[
                ['index' => 'title', 'label' => 'Description'],
                ['index' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                ['index' => 'unit_cost', 'label' => 'Unit cost', 'align' => 'right'],
                ['index' => 'line_total', 'label' => 'Line total', 'align' => 'right'],
            ]" :rows="$items">
                <x-slot:empty>No line items.</x-slot:empty>
            </x-table>
        </x-card>
    </div>

    {{-- ============================= COMMERCIAL ============================= --}}
    <div @if ($activeTab !== 'commercial') hidden @endif class="flex flex-col gap-4">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Payment milestones</span>
                    @if ($milestonesEditable)
                        <x-button text="New milestone" icon="plus" color="blue" sm wire:click="addMilestone" />
                    @endif
                </div>
            </x-slot:header>
            @unless ($milestonesEditable)
                <p class="text-xs text-gray-400 mb-2">Milestones are locked once the job leaves Draft — their total was already validated against the approved job value.</p>
            @endunless
            <x-table :headers="[
                ['index' => 'type_label', 'label' => 'Type'],
                ['index' => 'description', 'label' => 'Description'],
                ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['index' => 'percentage', 'label' => 'Percent', 'align' => 'right'],
                ['index' => 'due_date', 'label' => 'Due date'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$milestones">
                @interact('column_actions', $row, $milestonesEditable)
                    @if ($milestonesEditable)
                        <div class="flex items-center justify-end gap-2">
                            <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="editMilestone({{ $row['id'] }})" />
                            <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="deleteMilestone({{ $row['id'] }})" wire:confirm="Remove this milestone?" />
                        </div>
                    @endif
                @endinteract
                <x-slot:empty>No milestones recorded.</x-slot:empty>
            </x-table>
            <div class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-gray-800 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Milestones total</span>
                <span class="font-bold tabular-nums">{{ $milestonesTotal }}</span>
            </div>
        </x-card>

        <x-card>
            <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Source snapshot</span></x-slot:header>
            <p class="text-xs text-gray-400 mb-2">Frozen at job creation from the accepted quotation — never re-synced afterward, so this job's history can't be retroactively altered by later quotation edits.</p>
            @if ($sourceSnapshot)
                <pre class="text-[11px] leading-relaxed bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto max-h-80">{{ json_encode($sourceSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-sm text-gray-400">No snapshot recorded.</p>
            @endif
        </x-card>
    </div>

    {{-- ============================= BILLING ============================= --}}
    <div @if ($activeTab !== 'billing') hidden @endif class="flex flex-col gap-4">
        <x-card>
            <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Invoices billed against this job</span></x-slot:header>
            <x-table :headers="[
                ['index' => 'number', 'label' => 'Number'],
                ['index' => 'date', 'label' => 'Date'],
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
                ['index' => 'balance', 'label' => 'Balance', 'align' => 'right'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$invoices">
                @interact('column_status', $row)
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                @endinteract
                @interact('column_actions', $row)
                    <x-button icon="document-arrow-down" href="{{ $row['url'] }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                @endinteract
                <x-slot:empty>No invoices billed against this job yet.</x-slot:empty>
            </x-table>
        </x-card>

        <x-card>
            <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Payment milestones (reference)</span></x-slot:header>
            <x-table :headers="[
                ['index' => 'type_label', 'label' => 'Type'],
                ['index' => 'description', 'label' => 'Description'],
                ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['index' => 'due_date', 'label' => 'Due date'],
            ]" :rows="$milestones">
                <x-slot:empty>No milestones recorded.</x-slot:empty>
            </x-table>
        </x-card>
    </div>

    {{-- ============================= PROCUREMENT ============================= --}}
    <div @if ($activeTab !== 'procurement') hidden @endif class="flex flex-col gap-4">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Allocated job cost</span>
                    <span class="text-xs text-gray-400">Total allocated: {{ $allocatedCostTotal }}</span>
                </div>
            </x-slot:header>
            <p class="text-xs text-gray-400 mb-2">
                Vendor bill lines allocated to this job — allocation itself happens from the vendor bill's own Items tab
                (a shared purchase may serve multiple jobs), read-only here.
            </p>
            <x-table :headers="[
                ['index' => 'item_title', 'label' => 'Vendor bill line'],
                ['index' => 'vendor', 'label' => 'Vendor'],
                ['index' => 'po_number', 'label' => 'Purchase order'],
                ['index' => 'bill_number', 'label' => 'Vendor bill'],
                ['index' => 'amount', 'label' => 'Allocated amount', 'align' => 'right'],
                ['index' => 'created_at', 'label' => 'Date'],
            ]" :rows="$jobCostAllocations">
                <x-slot:empty>No vendor cost allocated to this job yet.</x-slot:empty>
            </x-table>
        </x-card>
    </div>

    {{-- ============================= DELIVERY ============================= --}}
    <div @if ($activeTab !== 'delivery') hidden @endif class="flex flex-col gap-4">
        <div class="grid lg:grid-cols-2 gap-4 items-start">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Delivery orders</span>
                        <x-button text="Record delivery" icon="plus" color="blue" sm wire:click="openDeliveryModal" />
                    </div>
                </x-slot:header>
                <x-table :headers="[
                    ['index' => 'number', 'label' => 'Number'],
                    ['index' => 'delivery_date', 'label' => 'Date'],
                    ['index' => 'items_count', 'label' => 'Lines', 'align' => 'right'],
                    ['index' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$deliveryOrders">
                    @interact('column_actions', $row)
                        <x-button icon="document-arrow-down" href="{{ $row['url'] }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    @endinteract
                    <x-slot:empty>No delivery orders recorded yet.</x-slot:empty>
                </x-table>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Handover reports</span>
                        @if ($overview['requiresHandover'])
                            <x-button text="Record handover" icon="plus" color="blue" sm wire:click="openHandoverModal" />
                        @endif
                    </div>
                </x-slot:header>
                @unless ($overview['requiresHandover'])
                    <p class="text-xs text-gray-400 mb-2">Goods-only job — handover is not required; this job closes operationally after delivery instead.</p>
                @endunless
                <x-table :headers="[
                    ['index' => 'number', 'label' => 'Number'],
                    ['index' => 'handover_date', 'label' => 'Date'],
                    ['index' => 'is_override', 'label' => 'Override'],
                    ['index' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$handoverReports">
                    @interact('column_is_override', $row)
                        @if ($row['is_override'])
                            <x-badge text="Override" color="amber" sm />
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    @endinteract
                    @interact('column_actions', $row)
                        <x-button icon="document-arrow-down" href="{{ $row['url'] }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    @endinteract
                    <x-slot:empty>No handover reports recorded yet.</x-slot:empty>
                </x-table>
            </x-card>
        </div>
    </div>

    {{-- ============================= MARGIN ============================= --}}
    <div @if ($activeTab !== 'margin') hidden @endif class="flex flex-col gap-4">
        <x-card>
            <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job margin</span></x-slot:header>
            <p class="text-xs text-gray-400 mb-3">
                Same convention as the Job Margin report: sales value, allocated gross cost, and unallocated
                purchasing cost are kept as three genuinely separate numbers, never blended into one figure.
            </p>
            <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
                <x-stats scope="compact" title="Sales value" icon="currency-dollar" color="blue">
                    <span class="text-sm font-bold tabular-nums">{{ $margin['salesValue'] }}</span>
                    <x-slot:footer>Invoiced total, excl. tax</x-slot:footer>
                </x-stats>
                <x-stats scope="compact" title="Allocated gross cost" icon="shopping-cart" color="amber">
                    <span class="text-sm font-bold tabular-nums">{{ $margin['allocatedCost'] }}</span>
                    <x-slot:footer>Vendor cost allocated here</x-slot:footer>
                </x-stats>
                <x-stats scope="compact" title="Margin" icon="chart-bar" :color="$margin['marginIsNegative'] ? 'red' : 'green'">
                    <span class="text-sm font-bold tabular-nums">{{ $margin['margin'] }}</span>
                    <x-slot:footer>Sales value − allocated cost</x-slot:footer>
                </x-stats>
                <x-stats scope="compact" title="Unallocated purchasing cost" icon="lock-closed" color="gray">
                    <span class="text-sm font-bold tabular-nums">{{ $margin['unallocatedPurchasingCost'] }}</span>
                    <x-slot:footer>Not yet allocated to any job</x-slot:footer>
                </x-stats>
            </div>
        </x-card>
    </div>

    {{-- ============================= ACTIVITY ============================= --}}
    <div @if ($activeTab !== 'activity') hidden @endif class="flex flex-col gap-4">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Job variations</span>
                    <x-button text="Record variation" icon="plus" color="blue" sm wire:click="openVariationModal" />
                </div>
            </x-slot:header>
            <p class="text-xs text-gray-400 mb-2">Overrun, out-of-scope work, and item substitutions — Owner/Admin approval only, each preserving the job's prior approved value.</p>
            <x-table :headers="[
                ['index' => 'type_label', 'label' => 'Type'],
                ['index' => 'reason', 'label' => 'Reason'],
                ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['index' => 'value_before', 'label' => 'Value before', 'align' => 'right'],
                ['index' => 'value_after', 'label' => 'Value after', 'align' => 'right'],
                ['index' => 'approved_by', 'label' => 'Approved by'],
                ['index' => 'approved_at', 'label' => 'Approved at'],
            ]" :rows="$variations">
                <x-slot:empty>No variations recorded.</x-slot:empty>
            </x-table>
        </x-card>

        <x-card>
            <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Status history</span></x-slot:header>
            <x-table :headers="[
                ['index' => 'action', 'label' => 'Event'],
                ['index' => 'user', 'label' => 'By'],
                ['index' => 'reason', 'label' => 'Reason'],
                ['index' => 'created_at', 'label' => 'When'],
            ]" :rows="$activityEvents">
                @interact('column_reason', $row)
                    <span class="text-gray-500 dark:text-gray-400">{{ $row['reason'] ?: '—' }}</span>
                @endinteract
                <x-slot:empty>No activity recorded yet.</x-slot:empty>
            </x-table>
        </x-card>
    </div>

    {{-- ============================= Modals ============================= --}}

    <x-modal wire="showAdvanceModal" title="Advance job status" center="sm">
        <x-select.styled wire:model="advanceTo" label="New status" required
            :options="$allowedNextStates->map(fn ($s) => ['label' => $s->getLabel(), 'value' => $s->value])->all()" />
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAdvanceModal', false)" />
            <x-button text="Advance" color="blue" wire:click="advance" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showCloseFinanciallyModal" title="Close financially" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Blocked automatically while an outstanding customer balance or unresolved vendor cost remains.
                Overriding requires Owner authorization, a reason, and an outstanding-balance summary — Admin may
                prepare but cannot finalize the override.
            </p>
            <x-toggle wire:model.live="closeOverride" label="Override the outstanding-balance / vendor-cost block" />
            @if ($closeOverride)
                <x-textarea wire:model="closeOverrideReason" label="Override reason" rows="3" required />
                <x-textarea wire:model="closeOutstandingSummary" label="Outstanding-balance summary" rows="3" required />
            @endif
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showCloseFinanciallyModal', false)" />
            <x-button text="Close financially" color="green" wire:click="closeFinancially" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showMilestoneModal" title="{{ $editingMilestoneId ? 'Edit milestone' : 'New milestone' }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model="milestone_type" label="Type" required
                :options="collect($milestoneTypes)->map(fn ($t) => ['label' => $t->getLabel(), 'value' => $t->value])->all()" />
            <x-input wire:model="milestone_description" label="Description" />
            <x-toggle wire:model.live="milestone_is_percentage" label="Amount is a percentage of job value" />
            @if ($milestone_is_percentage)
                <x-input wire:model.live="milestone_percentage" label="Percentage" type="number" step="0.01" suffix="%" />
            @endif
            <x-input wire:model="milestone_amount" label="Amount" type="number" step="0.01" :disabled="$milestone_is_percentage" />
            <x-date wire:model="milestone_due_date" label="Due date" />
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showMilestoneModal', false)" />
            <x-button text="Save milestone" color="blue" wire:click="saveMilestone" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showVariationModal" title="Record job variation" center="sm">
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model="variation_type" label="Type" required
                :options="collect($variationTypes)->map(fn ($t) => ['label' => $t->getLabel(), 'value' => $t->value])->all()" />
            <x-textarea wire:model="variation_reason" label="Reason" rows="3" required />
            <x-input wire:model="variation_amount" label="Amount" type="number" step="0.01" hint="Signed delta to the job value — positive for an overrun/added scope, negative for a reduction." />
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showVariationModal', false)" />
            <x-button text="Approve variation" color="blue" wire:click="recordVariation" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showDeliveryModal" title="Record delivery" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="delivery_notes" label="Notes" rows="2" />
            <div class="flex flex-col gap-2">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Delivered items</span>
                @foreach ($delivery_items as $i => $row)
                    <div class="grid grid-cols-[1.4fr_1fr_0.7fr_auto] gap-2 items-end">
                        <x-select.styled wire:model="delivery_items.{{ $i }}.sales_order_item_id" label="Job line" searchable
                            :options="$items->map(fn ($it) => ['label' => $it['title'], 'value' => (string) $it['id']])->all()" />
                        <x-input wire:model="delivery_items.{{ $i }}.description" label="Description" />
                        <x-input wire:model="delivery_items.{{ $i }}.quantity_delivered" label="Qty" type="number" step="0.0001" />
                        <x-button icon="trash" color="red" scope="icon-action" class="h-9 w-9" wire:click="removeDeliveryItemRow({{ $i }})" />
                    </div>
                @endforeach
                <x-button text="Add row" icon="plus" color="gray" sm wire:click="addDeliveryItemRow" />
            </div>
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showDeliveryModal', false)" />
            <x-button text="Record delivery" color="blue" wire:click="recordDelivery" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showHandoverModal" title="Record handover" center="sm">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="handover_notes" label="Notes" rows="2" />
            <x-toggle wire:model.live="handover_override" label="Override completeness requirement (Owner/Admin only)" />
            @if ($handover_override)
                <x-textarea wire:model="handover_override_reason" label="Override reason" rows="3" required />
            @endif
        </div>
        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showHandoverModal', false)" />
            <x-button text="Record handover" color="blue" wire:click="recordHandover" />
        </x-slot:footer>
    </x-modal>
</div>
