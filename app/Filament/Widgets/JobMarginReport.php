<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\JobCostAllocation;
use App\Models\SalesOrder;
use App\Models\VendorBillItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Job margin clearly distinguishes allocated gross cost from unallocated
 * purchasing cost, and operational closure never falsely implies
 * financial settlement." — docs/rebuild/specs/05-procurement-and-delivery/
 * Specs.md acceptance criteria, deferred to Phase 06 (see
 * docs/rebuild/outputs/20-phase-05-checkpoint-report.md). Margin follows
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3: "Job margin equals
 * customer sales after discounts and excluding customer tax, less
 * allocated gross vendor/direct cost." — sales value is each linked
 * invoice's `total` (already post-discount) minus its `tax_total`,
 * excluding Void/Amended/Cancelled invoices, same exclusion list
 * App\Actions\Sales\CloseJobFinancially already uses for "a real
 * receivable."
 *
 * Allocated gross cost and unallocated purchasing cost are kept as two
 * separate columns — never blended into one "cost" figure — per the
 * acceptance criterion's literal wording. Unallocated purchasing cost is
 * scoped per job: the sum of App\Models\VendorBillItem::unallocatedAmount()
 * across every vendor bill item that has at least one
 * App\Models\JobCostAllocation pointing at this job — "purchasing cost
 * that touched this job's vendor bills but hasn't been fully allocated
 * anywhere yet," computed with two small aggregate queries up front
 * (not one query per row) rather than N+1 per-record calls.
 *
 * Company-scoped via App\Models\Concerns\BelongsToCompany's tenant global
 * scope (same as every other resource/widget); paginated by Filament's
 * table() default (not disabled here).
 */
class JobMarginReport extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    // See App\Filament\Widgets\RevenueOverview's $isLazy for why.
    protected static bool $isLazy = false;

    private const EXCLUDED_INVOICE_STATUSES = [
        InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled,
    ];

    /** @var Collection<int, float>|null keyed by sales_order_id */
    private ?Collection $unallocatedBySalesOrder = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Job margin')
            ->query(
                SalesOrder::query()
                    ->with('client')
                    ->withSum(['invoices as sales_total' => fn (Builder $query) => $query->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)], 'total')
                    ->withSum(['invoices as sales_tax_total' => fn (Builder $query) => $query->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)], 'tax_total')
                    ->withSum('jobCostAllocations as allocated_cost', 'amount')
            )
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sales_value')
                    ->label('Sales value')
                    ->state(fn (SalesOrder $record) => $this->salesValue($record))
                    ->numeric(2),
                TextColumn::make('allocated_cost')
                    ->label('Allocated gross cost')
                    ->state(fn (SalesOrder $record) => round((float) $record->allocated_cost, 2))
                    ->numeric(2),
                TextColumn::make('margin')
                    ->label('Margin')
                    ->state(fn (SalesOrder $record) => round($this->salesValue($record) - round((float) $record->allocated_cost, 2), 2))
                    ->numeric(2)
                    ->color(fn (SalesOrder $record) => round($this->salesValue($record) - round((float) $record->allocated_cost, 2), 2) < 0 ? 'danger' : 'success'),
                TextColumn::make('unallocated_purchasing_cost')
                    ->label('Unallocated purchasing cost')
                    ->state(fn (SalesOrder $record) => $this->unallocatedPurchasingCostFor($record))
                    ->numeric(2)
                    ->color('warning'),
            ]);
    }

    /** Customer sales after discounts, excluding customer tax: total - tax_total. */
    private function salesValue(SalesOrder $record): float
    {
        return round((float) $record->sales_total - (float) $record->sales_tax_total, 2);
    }

    /**
     * Every vendor bill item touching this job (i.e. with at least one
     * allocation pointing at it), summing that item's *own*
     * unallocatedAmount() — the remainder not yet allocated anywhere,
     * across every job, not just this one. Computed once for the whole
     * table via {@see loadUnallocatedPurchasingCosts()}.
     */
    private function unallocatedPurchasingCostFor(SalesOrder $record): float
    {
        return $this->loadUnallocatedPurchasingCosts()->get($record->id, 0.0);
    }

    /** @return Collection<int, float> keyed by sales_order_id */
    private function loadUnallocatedPurchasingCosts(): Collection
    {
        if ($this->unallocatedBySalesOrder !== null) {
            return $this->unallocatedBySalesOrder;
        }

        // Distinct (sales_order_id, vendor_bill_item_id) pairs — a job may
        // have more than one allocation row against the same source item.
        $pairs = JobCostAllocation::query()
            ->whereHas('salesOrder')
            ->select('sales_order_id', 'vendor_bill_item_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return $this->unallocatedBySalesOrder = collect();
        }

        $itemIds = $pairs->pluck('vendor_bill_item_id')->unique()->values();

        // Total allocated per item across ALL jobs (not just this one) —
        // needed to compute each item's true remainder.
        $allocatedTotals = JobCostAllocation::query()
            ->whereIn('vendor_bill_item_id', $itemIds)
            ->selectRaw('vendor_bill_item_id, sum(amount) as total')
            ->groupBy('vendor_bill_item_id')
            ->pluck('total', 'vendor_bill_item_id');

        $lineTotals = VendorBillItem::query()->whereIn('id', $itemIds)->pluck('line_total', 'id');

        $result = collect();

        foreach ($pairs as $pair) {
            $itemId = $pair->vendor_bill_item_id;
            $unallocated = round(
                (float) ($lineTotals[$itemId] ?? 0) - (float) ($allocatedTotals[$itemId] ?? 0),
                2
            );

            $result->put(
                $pair->sales_order_id,
                round($result->get($pair->sales_order_id, 0.0) + max(0.0, $unallocated), 2)
            );
        }

        return $this->unallocatedBySalesOrder = $result;
    }
}
