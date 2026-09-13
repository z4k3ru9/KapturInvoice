<?php

namespace App\Actions\Sales;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Models\SalesOrder;
use App\Models\User;
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

        if ($outstandingBalance > 0.01) {
            if (! $override) {
                throw new RuntimeException(
                    "Financial closure is blocked by an outstanding customer balance of {$outstandingBalance}; use the override with Owner authorization and a reason."
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
}
