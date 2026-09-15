<?php

namespace App\Livewire;

use App\Actions\Delivery\CompleteDelivery;
use App\Actions\Delivery\CompleteHandover;
use App\Actions\Sales\ApproveJobVariation;
use App\Actions\Sales\ApproveSalesOrder;
use App\Actions\Sales\CloseJobFinancially;
use App\Actions\Sales\CloseJobOperationally;
use App\Actions\Sales\TransitionSalesOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobVariationType;
use App\Enums\MilestoneType;
use App\Enums\SalesOrderStatus;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\HandoverReport;
use App\Models\JobVariation;
use App\Models\PaymentMilestone;
use App\Models\SalesOrder;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack Job (SalesOrder) workspace — the 7-tab detail view
 * (Overview/Activity/Billing/Commercial/Delivery/Procurement/Margin)
 * matching the Stitch "Job Workspace" mockups. Mirrors
 * App\Filament\Resources\SalesOrders (the Infolist + every relation
 * manager: Items, Milestones, Variations, DeliveryOrders,
 * HandoverReports) field-for-field and action-for-action — every
 * mutation goes through the exact same App\Actions\Sales / App\Actions\Delivery
 * class the Filament table/relation-manager actions call, with the same
 * role checks enforced inside those classes. `status` is never set
 * directly.
 *
 * A job has no create/edit page (it is only ever created from an
 * Accepted quotation via App\Actions\Sales\CreateSalesOrderFromQuotation
 * — see SalesOrderResource's own docblock), so this component is
 * view+action only, never a create form.
 */
#[Layout('components.tallstack.app')]
class TallStackSalesOrder extends Component
{
    use Interactions;

    public Company $company;

    public SalesOrder $salesOrder;

    public string $activeTab = 'overview';

    // --- Advance status modal -------------------------------------------------
    public bool $showAdvanceModal = false;

    public ?string $advanceTo = null;

    // --- Close financially modal -----------------------------------------------
    public bool $showCloseFinanciallyModal = false;

    public bool $closeOverride = false;

    public ?string $closeOverrideReason = null;

    public ?string $closeOutstandingSummary = null;

    // --- Milestone modal (Commercial tab) — mirrors MilestonesRelationManager's own form. ---
    public bool $showMilestoneModal = false;

    public ?int $editingMilestoneId = null;

    public string $milestone_type = MilestoneType::Custom->value;

    public ?string $milestone_description = null;

    public bool $milestone_is_percentage = false;

    public ?float $milestone_percentage = null;

    public float $milestone_amount = 0;

    public ?string $milestone_due_date = null;

    // --- Variation modal (Activity tab) — mirrors VariationsRelationManager's own form. ---
    public bool $showVariationModal = false;

    public string $variation_type = JobVariationType::Overrun->value;

    public ?string $variation_reason = null;

    public float $variation_amount = 0;

    // --- Delivery modal (Delivery tab) — mirrors DeliveryOrdersRelationManager's own form. ---
    public bool $showDeliveryModal = false;

    public ?string $delivery_notes = null;

    /** @var array<int, array{sales_order_item_id: ?string, description: ?string, quantity_delivered: float}> */
    public array $delivery_items = [];

    // --- Handover modal (Delivery tab) — mirrors HandoverReportsRelationManager's own form. ---
    public bool $showHandoverModal = false;

    public ?string $handover_notes = null;

    public bool $handover_override = false;

    public ?string $handover_override_reason = null;

    public function mount(Company $company, SalesOrder $salesOrder): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `salesOrder` happens before this mount() body
        // runs and before app(Tenancy::class)->set() above activates
        // BelongsToCompany's scope — cross-company access is checked
        // explicitly here, the same reasoning as
        // TallStackQuotationForm::mount().
        abort_unless($salesOrder->company_id === $company->id, 404);

        $this->salesOrder = $salesOrder->load(['client', 'quotation', 'items']);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // --- Status transitions — identical actions to SalesOrdersTable's own row actions. -----

    public function approve(): void
    {
        $this->authorize('update', $this->salesOrder);

        try {
            app(ApproveSalesOrder::class)->approve($this->salesOrder);
            $this->salesOrder->refresh();
            $this->toast()->success('Job approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve job', $e->getMessage())->send();
        }
    }

    public function openAdvanceModal(): void
    {
        $this->advanceTo = null;
        $this->showAdvanceModal = true;
    }

    public function advance(): void
    {
        $this->authorize('update', $this->salesOrder);

        if (! $this->advanceTo) {
            return;
        }

        try {
            app(TransitionSalesOrderStatus::class)->transition($this->salesOrder, SalesOrderStatus::from($this->advanceTo));
            $this->salesOrder->refresh();
            $this->showAdvanceModal = false;
            $this->toast()->success('Job updated.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not update job', $e->getMessage())->send();
        }
    }

    public function cancel(): void
    {
        $this->authorize('update', $this->salesOrder);

        try {
            app(TransitionSalesOrderStatus::class)->transition($this->salesOrder, SalesOrderStatus::Cancelled);
            $this->salesOrder->refresh();
            $this->toast()->success('Job cancelled.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not cancel job', $e->getMessage())->send();
        }
    }

    public function closeOperationally(): void
    {
        $this->authorize('update', $this->salesOrder);

        try {
            $this->salesOrder = app(CloseJobOperationally::class)->close($this->salesOrder)->load(['client', 'quotation', 'items']);
            $this->toast()->success('Job closed operationally.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not close job operationally', $e->getMessage())->send();
        }
    }

    public function openCloseFinanciallyModal(): void
    {
        $this->closeOverride = false;
        $this->closeOverrideReason = null;
        $this->closeOutstandingSummary = null;
        $this->showCloseFinanciallyModal = true;
    }

    public function closeFinancially(): void
    {
        $this->authorize('update', $this->salesOrder);

        try {
            $this->salesOrder = app(CloseJobFinancially::class)->close(
                $this->salesOrder,
                auth()->user(),
                $this->closeOverride,
                $this->closeOverrideReason,
                $this->closeOutstandingSummary,
            )->load(['client', 'quotation', 'items']);

            $this->showCloseFinanciallyModal = false;
            $this->toast()->success('Job closed financially.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not close job financially', $e->getMessage())->send();
        }
    }

    // --- Commercial tab: payment milestones — same direct model CRUD as
    // MilestonesRelationManager (no dedicated Action class exists for
    // this on the Filament side either), gated the same way to Draft-only. ---

    public function addMilestone(): void
    {
        $this->resetMilestoneForm();
        $this->showMilestoneModal = true;
    }

    public function editMilestone(int $id): void
    {
        $milestone = $this->scopedMilestone($id);

        if (! $milestone) {
            return;
        }

        $this->editingMilestoneId = $milestone->id;
        $this->milestone_type = $milestone->type->value;
        $this->milestone_description = $milestone->description;
        $this->milestone_is_percentage = $milestone->is_percentage;
        $this->milestone_percentage = $milestone->percentage !== null ? (float) $milestone->percentage : null;
        $this->milestone_amount = (float) $milestone->amount;
        $this->milestone_due_date = $milestone->due_date?->toDateString();
        $this->showMilestoneModal = true;
    }

    public function updatedMilestonePercentage(): void
    {
        $this->recalculateMilestoneAmount();
    }

    public function updatedMilestoneIsPercentage(): void
    {
        $this->recalculateMilestoneAmount();
    }

    private function recalculateMilestoneAmount(): void
    {
        if (! $this->milestone_is_percentage) {
            return;
        }

        $this->milestone_amount = round(((float) $this->milestone_percentage / 100) * (float) $this->salesOrder->approved_value, 2);
    }

    public function saveMilestone(): void
    {
        if (! $this->isMilestonesEditable()) {
            return;
        }

        $this->authorize('update', $this->salesOrder);

        $data = $this->validate([
            'milestone_type' => ['required'],
            'milestone_description' => ['nullable', 'string', 'max:255'],
            'milestone_is_percentage' => ['boolean'],
            'milestone_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'milestone_amount' => ['required', 'numeric', 'min:0'],
            'milestone_due_date' => ['nullable', 'date'],
        ]);

        $payload = [
            'type' => $data['milestone_type'],
            'description' => $data['milestone_description'],
            'is_percentage' => $data['milestone_is_percentage'],
            'percentage' => $data['milestone_is_percentage'] ? $data['milestone_percentage'] : null,
            'amount' => $data['milestone_amount'],
            'due_date' => $data['milestone_due_date'],
        ];

        if ($this->editingMilestoneId) {
            $milestone = $this->scopedMilestone($this->editingMilestoneId);
            $milestone?->update($payload);
        } else {
            $payload['sales_order_id'] = $this->salesOrder->id;
            $payload['sort_order'] = ((int) $this->salesOrder->milestones()->max('sort_order')) + 1;
            PaymentMilestone::create($payload);
        }

        $this->salesOrder->load('milestones');
        $this->showMilestoneModal = false;
        $this->resetMilestoneForm();
        $this->toast()->success('Milestone saved.')->send();
    }

    public function deleteMilestone(int $id): void
    {
        if (! $this->isMilestonesEditable()) {
            return;
        }

        $this->authorize('update', $this->salesOrder);

        $milestone = $this->scopedMilestone($id);
        $milestone?->delete();
        $this->salesOrder->load('milestones');
        $this->toast()->success('Milestone removed.')->send();
    }

    private function resetMilestoneForm(): void
    {
        $this->editingMilestoneId = null;
        $this->milestone_type = MilestoneType::Custom->value;
        $this->milestone_description = null;
        $this->milestone_is_percentage = false;
        $this->milestone_percentage = null;
        $this->milestone_amount = 0;
        $this->milestone_due_date = null;
    }

    private function scopedMilestone(int $id): ?PaymentMilestone
    {
        return $this->salesOrder->milestones->firstWhere('id', $id);
    }

    /** Same rule as MilestonesRelationManager::isEditable(). */
    private function isMilestonesEditable(): bool
    {
        return $this->salesOrder->status === SalesOrderStatus::Draft;
    }

    // --- Activity tab: job variations — every row written only through
    // App\Actions\Sales\ApproveJobVariation, same as VariationsRelationManager. ---

    public function openVariationModal(): void
    {
        $this->variation_type = JobVariationType::Overrun->value;
        $this->variation_reason = null;
        $this->variation_amount = 0;
        $this->showVariationModal = true;
    }

    public function recordVariation(): void
    {
        $data = $this->validate([
            'variation_type' => ['required'],
            'variation_reason' => ['required', 'string'],
            'variation_amount' => ['required', 'numeric'],
        ]);

        try {
            app(ApproveJobVariation::class)->approve(
                $this->salesOrder,
                auth()->user(),
                JobVariationType::from($data['variation_type']),
                $data['variation_reason'],
                (float) $data['variation_amount'],
            );

            $this->salesOrder->refresh()->load('variations');
            $this->showVariationModal = false;
            $this->toast()->success('Variation approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve variation', $e->getMessage())->send();
        }
    }

    // --- Delivery tab: Delivery Orders / Handover Reports — every row
    // written only through App\Actions\Delivery\*, same as the Filament
    // relation managers. ---

    public function openDeliveryModal(): void
    {
        $this->delivery_notes = null;
        $this->delivery_items = [
            ['sales_order_item_id' => null, 'description' => null, 'quantity_delivered' => 0],
        ];
        $this->showDeliveryModal = true;
    }

    public function addDeliveryItemRow(): void
    {
        $this->delivery_items[] = ['sales_order_item_id' => null, 'description' => null, 'quantity_delivered' => 0];
    }

    public function removeDeliveryItemRow(int $index): void
    {
        unset($this->delivery_items[$index]);
        $this->delivery_items = array_values($this->delivery_items);
    }

    public function recordDelivery(): void
    {
        $items = array_values(array_filter($this->delivery_items, fn ($row) => (float) ($row['quantity_delivered'] ?? 0) > 0));

        if (empty($items)) {
            $this->toast()->error('Could not record delivery', 'At least one item with a delivered quantity is required.')->send();

            return;
        }

        try {
            app(CompleteDelivery::class)->complete(
                $this->salesOrder,
                auth()->user(),
                array_map(fn ($row) => [
                    'sales_order_item_id' => filled($row['sales_order_item_id']) ? (int) $row['sales_order_item_id'] : null,
                    'description' => $row['description'] ?? null,
                    'quantity_delivered' => (float) $row['quantity_delivered'],
                ], $items),
                $this->delivery_notes,
            );

            $this->salesOrder->refresh()->load(['deliveryOrders.items', 'items']);
            $this->showDeliveryModal = false;
            $this->toast()->success('Delivery recorded.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not record delivery', $e->getMessage())->send();
        }
    }

    public function openHandoverModal(): void
    {
        $this->handover_notes = null;
        $this->handover_override = false;
        $this->handover_override_reason = null;
        $this->showHandoverModal = true;
    }

    public function recordHandover(): void
    {
        try {
            app(CompleteHandover::class)->complete(
                $this->salesOrder,
                auth()->user(),
                $this->handover_notes,
                $this->handover_override,
                $this->handover_override_reason,
            );

            $this->salesOrder->refresh()->load('handoverReports');
            $this->showHandoverModal = false;
            $this->toast()->success('Handover recorded.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not record handover', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;
        $job = $this->salesOrder;

        $job->loadMissing([
            'client', 'quotation', 'items', 'milestones', 'variations.approvedBy',
            'deliveryOrders.items', 'deliveryOrders.createdBy',
            'handoverReports.createdBy', 'invoices',
        ]);

        return view('livewire.tallstack-sales-order', [
            'currency' => $currency,
            'statusColor' => StatusColor::map($job->status->getColor()),
            'allowedNextStates' => collect($job->status->allowedNextStates())
                ->reject(fn (SalesOrderStatus $s) => $s === SalesOrderStatus::Cancelled)
                ->values(),
            'milestoneTypes' => MilestoneType::cases(),
            'variationTypes' => JobVariationType::cases(),

            // --- Overview tab -------------------------------------------------
            'overview' => [
                'approvedValue' => Money::format((float) $job->approved_value, $currency),
                'paidAmount' => Money::format($this->paidAmount(), $currency),
                'outstandingBalance' => Money::format($this->outstandingBalance(), $currency),
                'itemsTotal' => Money::format((float) $job->items->sum('line_total'), $currency),
                'isFullyDelivered' => $job->isFullyDelivered(),
                'requiresHandover' => $job->requires_handover,
            ],
            'items' => $job->items->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'quantity' => (float) $item->quantity,
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                'line_total' => Money::format((float) $item->line_total, $currency),
            ]),

            // --- Commercial tab -------------------------------------------------
            'milestones' => $job->milestones->map(fn (PaymentMilestone $m) => [
                'id' => $m->id,
                'type_label' => $m->type->getLabel(),
                'description' => $m->description ?: '—',
                'amount' => Money::format((float) $m->amount, $currency),
                'is_percentage' => $m->is_percentage,
                'percentage' => $m->percentage !== null ? ((float) $m->percentage).'%' : '—',
                'due_date' => $m->due_date?->format('d M Y') ?? '—',
            ]),
            'milestonesTotal' => Money::format((float) $job->milestones->sum('amount'), $currency),
            'milestonesEditable' => $this->isMilestonesEditable(),
            'sourceSnapshot' => $job->source_snapshot,

            // --- Billing tab -------------------------------------------------
            'invoices' => $job->invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number ?? '—',
                'status' => $invoice->status,
                'status_label' => $invoice->status->getLabel(),
                'status_color' => StatusColor::map($invoice->status->getColor()),
                'total' => Money::format((float) $invoice->total, $currency),
                'balance' => Money::format((float) $invoice->balance, $currency),
                'date' => $invoice->invoice_date?->format('d M Y') ?? '—',
                'url' => route('invoices.pdf', $invoice),
            ]),

            // --- Activity tab -------------------------------------------------
            'variations' => $job->variations->map(fn (JobVariation $v) => [
                'type_label' => $v->type->getLabel(),
                'reason' => $v->reason,
                'amount' => Money::format((float) $v->amount, $currency),
                'value_before' => Money::format((float) $v->value_before, $currency),
                'value_after' => Money::format((float) $v->value_after, $currency),
                'approved_by' => $v->approvedBy?->name ?? '—',
                'approved_at' => $v->approved_at?->format('d M Y H:i') ?? '—',
            ]),
            'activityEvents' => $this->activityEvents(),

            // --- Delivery tab -------------------------------------------------
            'deliveryOrders' => $job->deliveryOrders->map(fn (DeliveryOrder $d) => [
                'id' => $d->id,
                'number' => $d->number,
                'delivery_date' => $d->delivery_date?->format('d M Y') ?? '—',
                'notes' => $d->notes ?: '—',
                'created_by' => $d->createdBy?->name ?? '—',
                'items_count' => $d->items->count(),
                'url' => route('delivery-orders.pdf', $d),
            ]),
            'handoverReports' => $job->handoverReports->map(fn (HandoverReport $h) => [
                'id' => $h->id,
                'number' => $h->number,
                'handover_date' => $h->handover_date?->format('d M Y') ?? '—',
                'is_override' => $h->is_override,
                'override_reason' => $h->override_reason ?: '—',
                'created_by' => $h->createdBy?->name ?? '—',
                'url' => route('handover-reports.pdf', $h),
            ]),

            // --- Procurement tab -------------------------------------------------
            'jobCostAllocations' => $this->jobCostAllocations($currency),
            'allocatedCostTotal' => Money::format($this->allocatedCost(), $currency),

            // --- Margin tab — same three-number convention as
            // App\Filament\Widgets\JobMarginReport, computed for this one job. ---
            'margin' => [
                'salesValue' => Money::format($this->salesValue(), $currency),
                'allocatedCost' => Money::format($this->allocatedCost(), $currency),
                'margin' => Money::format($this->salesValue() - $this->allocatedCost(), $currency),
                'marginIsNegative' => ($this->salesValue() - $this->allocatedCost()) < 0,
                'unallocatedPurchasingCost' => Money::format($this->unallocatedPurchasingCost(), $currency),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'jobs',
            'title' => "Job {$job->number}",
        ]);
    }

    private function paidAmount(): float
    {
        return round((float) $this->salesOrder->invoices->sum(fn ($i) => (float) $i->total - (float) $i->balance), 2);
    }

    private function outstandingBalance(): float
    {
        return round(
            (float) $this->salesOrder->invoices
                ->whereNotIn('status', [InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled])
                ->sum('balance'),
            2
        );
    }

    /** Customer sales after discounts, excluding customer tax — same formula as JobMarginReport::salesValue(). */
    private function salesValue(): float
    {
        $invoices = $this->salesOrder->invoices->whereNotIn('status', [
            InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled,
        ]);

        return round((float) $invoices->sum('total') - (float) $invoices->sum('tax_total'), 2);
    }

    private function allocatedCost(): float
    {
        return round((float) $this->salesOrder->jobCostAllocations()->sum('amount'), 2);
    }

    /**
     * Same formula as JobMarginReport::loadUnallocatedPurchasingCosts(),
     * scoped to this one job: for every VendorBillItem with at least one
     * allocation pointing here, its own remainder not yet allocated
     * anywhere (across every job that item touches).
     */
    private function unallocatedPurchasingCost(): float
    {
        $items = $this->salesOrder->jobCostAllocations()->with('vendorBillItem')->get()
            ->pluck('vendorBillItem')->filter()->unique('id');

        return round($items->sum(fn ($item) => max(0.0, $item->unallocatedAmount())), 2);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function jobCostAllocations(string $currency): Collection
    {
        return $this->salesOrder->jobCostAllocations()
            ->with(['vendorBillItem.vendorBill.vendorPurchaseOrder.vendor', 'createdBy'])
            ->latest('id')
            ->get()
            ->map(function ($allocation) use ($currency) {
                $item = $allocation->vendorBillItem;
                $bill = $item?->vendorBill;
                $po = $bill?->vendorPurchaseOrder;

                return [
                    'item_title' => $item?->title ?? '—',
                    'vendor' => $po?->vendor?->name ?? '—',
                    'bill_number' => $bill?->number ?? '—',
                    'po_number' => $po?->number ?? '—',
                    'amount' => Money::format((float) $allocation->amount, $currency),
                    'allocated_by' => $allocation->createdBy?->name ?? '—',
                    'created_at' => $allocation->created_at?->format('d M Y') ?? '—',
                ];
            });
    }

    /**
     * Real status/variation/delivery/handover history for this job, from
     * the append-only audit log (App\Services\AuditLogger) — no morph
     * map is registered, so `entity_type` is stored as the full class
     * name. Covers events logged directly against the job (status
     * changes, approval, operational/financial closure, cancellation)
     * plus events logged against its child records (variations,
     * deliveries, handovers), unioned and sorted by time.
     */
    private function activityEvents(): Collection
    {
        $job = $this->salesOrder;

        $variationIds = $job->variations->pluck('id');
        $deliveryIds = $job->deliveryOrders->pluck('id');
        $handoverIds = $job->handoverReports->pluck('id');

        return AuditEvent::query()
            ->where('company_id', $this->company->id)
            ->where(function ($query) use ($job, $variationIds, $deliveryIds, $handoverIds) {
                $query->where(function ($q) use ($job) {
                    $q->where('entity_type', SalesOrder::class)->where('entity_id', $job->id);
                });

                if ($variationIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($variationIds) {
                        $q->where('entity_type', JobVariation::class)->whereIn('entity_id', $variationIds);
                    });
                }

                if ($deliveryIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($deliveryIds) {
                        $q->where('entity_type', DeliveryOrder::class)->whereIn('entity_id', $deliveryIds);
                    });
                }

                if ($handoverIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($handoverIds) {
                        $q->where('entity_type', HandoverReport::class)->whereIn('entity_id', $handoverIds);
                    });
                }
            })
            ->with('actor')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AuditEvent $event) => [
                'action' => str($event->action)->replace('_', ' ')->replace('.', ' — ')->title()->toString(),
                'user' => $event->actor?->name ?? 'System',
                'reason' => $event->reason,
                'created_at' => $event->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }
}
