<?php

namespace App\Actions\Sales;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Models\JobCostAllocation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\VendorBillItem;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Financial closure with unpaid customer balances, unknown vendor cost,
 * or exceptional reconciliation is Owner-only, requires an
 * outstanding-balance summary and reason, and is fully audited. Admin may
 * prepare but cannot finalize the override." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4,
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md ("Financial
 * closure blocked by unpaid customer invoice unless authorized
 * override"). Void/Amended/Cancelled invoices are excluded from the
 * outstanding-balance check — their stale balance is not a real
 * receivable. Independent of operational closure — see
 * App\Actions\Sales\CloseJobOperationally and
 * App\Models\SalesOrder::isFullyClosed().
 *
 * "Unknown vendor cost" also gates the override — a Codex review finding
 * on PR #4: a job with zero outstanding customer balance but genuinely
 * unresolved purchasing cost still closed cleanly, so its margin
 * (App\Filament\Widgets\JobMarginReport) could be finalized on an
 * unreliable number. Reuses that same widget's own "unallocated
 * purchasing cost" definition: the remainder of every VendorBillItem
 * touching this job (via at least one JobCostAllocation) that isn't yet
 * allocated anywhere, across every job that item touches.
 */
class CloseJobFinancially
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function close(
        SalesOrder $salesOrder,
        User $actor,
        bool $override = false,
        ?string $overrideReason = null,
        ?string $outstandingBalanceSummary = null,
    ): SalesOrder {
        if ($salesOrder->financial_closed_at !== null) {
            throw new RuntimeException('This job is already financially closed.');
        }

        $outstandingBalance = round((float) $salesOrder->invoices()
            ->whereNotIn('status', [InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled])
            ->sum('balance'), 2);

        $unresolvedVendorCost = $this->unresolvedVendorCost($salesOrder);

        if ($outstandingBalance > 0.01 || $unresolvedVendorCost > 0.01) {
            if (! $override) {
                $reasons = array_filter([
                    $outstandingBalance > 0.01 ? "an outstanding customer balance of {$outstandingBalance}" : null,
                    $unresolvedVendorCost > 0.01 ? "unresolved vendor cost of {$unresolvedVendorCost}" : null,
                ]);

                throw new RuntimeException(
                    'Financial closure is blocked by '.implode(' and ', $reasons).'; use the override with Owner authorization and a reason.'
                );
            }

            if (! $actor->hasCompanyRole($salesOrder->company, CompanyRole::Owner)) {
                throw new RuntimeException('Only Owner may finalize a financial closure override; Admin may prepare but not finalize it.');
            }

            if (blank($overrideReason) || blank($outstandingBalanceSummary)) {
                throw new RuntimeException('An outstanding-balance summary and reason are both required to override financial closure.');
            }
        }

        $salesOrder->forceFill(['financial_closed_at' => now()])->save();

        $after = ['financial_closed_at' => $salesOrder->financial_closed_at];

        if ($override) {
            $after['outstanding_balance'] = (string) $outstandingBalance;
            $after['unresolved_vendor_cost'] = (string) $unresolvedVendorCost;
            $after['summary'] = $outstandingBalanceSummary;
        }

        $this->auditLogger->record(
            $salesOrder->company,
            'sales_order.financially_closed',
            $salesOrder,
            ['financial_closed_at' => null],
            $after,
            $override ? $overrideReason : null,
        );

        return $salesOrder->fresh();
    }

    private function unresolvedVendorCost(SalesOrder $salesOrder): float
    {
        $itemIds = JobCostAllocation::query()
            ->where('sales_order_id', $salesOrder->id)
            ->pluck('vendor_bill_item_id')
            ->unique();

        if ($itemIds->isEmpty()) {
            return 0.0;
        }

        return round(
            VendorBillItem::query()
                ->whereIn('id', $itemIds)
                ->get()
                ->sum(fn (VendorBillItem $item) => $item->unallocatedAmount()),
            2
        );
    }
}
