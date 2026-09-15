<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JobCostAllocation;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\TaxRecap;
use App\Models\VendorBillItem;
use App\Support\Dashboard\DashboardPeriod;
use App\Support\Dashboard\Money;
use App\Support\Dashboard\RevenueBuckets;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 10 (docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md) —
 * "Financial Analytics & Tax Reports", TALL-stack-native. There is no
 * dedicated Filament page for this area — the closest existing sources of
 * truth are the equivalent pre-TallStackUI Filament dashboard's own
 * widgets (RevenueOverview/RevenueTrendChart, ported into
 * App\Livewire\TallStackDashboard the same way already) and that same
 * admin's JobMarginReport widget (the job-cost/margin report). This page
 * is a presentation-layer combination of those three data sources plus a
 * real listing of App\Models\TaxRecap rows — it introduces no new
 * calculation of its own:
 *
 * - Financial Analytics: the exact same revenue/outstanding/overdue
 *   aggregate queries App\Livewire\TallStackDashboard already runs
 *   (itself a direct port of the equivalent Filament RevenueOverview
 *   widget), plus the same App\Support\Dashboard\RevenueBuckets-driven
 *   trend chart, with its own independent period selector (not shared
 *   with the Dashboard's own $period, since a report page is commonly
 *   viewed over a different window than "this month").
 * - Job margin: the equivalent Filament JobMarginReport widget's own
 *   query/columns/computation, ported verbatim (sales value = invoice
 *   total minus tax, excluding Void/Amended/Cancelled; allocated gross
 *   cost via a withSum on jobCostAllocations; unallocated purchasing cost
 *   computed the same two-aggregate-query way as the widget) — never
 *   blended into one number, per this project's own standing rule.
 *   Paginated (10/page), unlike the widget which relied on Filament's own
 *   table pagination.
 * - Tax Reports: a real, read-only list of this company's TaxRecap rows
 *   (via each row's owning Invoice) with a status derived the same way
 *   the equivalent pre-TallStackUI Filament invoice infolist's own
 *   fileOrAdjustTaxRecap action already treats these columns (pending/
 *   filed/adjusted — see that class and App\Actions\Billing\
 *   FileOrAdjustTaxRecap for the exact semantics reused here), each row
 *   linking to the existing tax-recaps.pdf download route unmodified. For
 *   a company with tax disabled (CompanyTaxSetting::tax_enabled = false,
 *   e.g. Karunia Abadi) this section renders an explanatory disabled
 *   state instead of an empty table — matching the same "explain why,
 *   don't just look broken" pattern already used on Credits' info line
 *   and Tax Rates' tax-disabled empty state.
 *
 * Access: no report-specific role gate exists anywhere in this codebase
 * (App\Enums\CompanyRole has no reportViewingRoles()/analyticsRoles()
 * helper, and JobMarginReport itself declares no canView() restriction
 * beyond the panel's own tenant membership check) — so, matching that
 * precedent, this page is reachable by any authenticated member of the
 * company, the same access level every other read-mostly TALL-stack
 * register already uses.
 */
#[Layout('components.tallstack.app')]
class TallStackReports extends Component
{
    use WithPagination;

    private const EXCLUDED_INVOICE_STATUSES = [
        InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled,
    ];

    public Company $company;

    public string $period = 'this_month';

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // ActionQueue/Resource URL generation isn't used on this page, but
        // every other TALL-stack page sets panel/tenant context in
        // mount() for consistency and in case a future addition needs it
        // (e.g. linking a job-margin row to its Filament SalesOrder view).
        app(Tenancy::class)->set($this->company);
    }

    public function updatingPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;
        $period = DashboardPeriod::resolve(['period' => $this->period]);
        $bucket = RevenueBuckets::forPeriod($period);

        $revenue = $this->companyPayments()
            ->whereBetween('payment_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount');

        $outstanding = $this->openInvoices();
        $overdue = $this->openInvoices()->where('due_date', '<', today());

        return view('livewire.tallstack-reports', [
            'periods' => DashboardPeriod::PERIODS,
            'periodLabel' => $period['label'],
            'currency' => $currency,
            'stats' => [
                'revenue' => Money::format($revenue, $currency),
                'outstanding' => Money::format((clone $outstanding)->sum('balance'), $currency),
                'outstandingCount' => (clone $outstanding)->count(),
                'overdueCount' => (clone $overdue)->count(),
                'overdueTotal' => Money::format((clone $overdue)->sum('balance'), $currency),
            ],
            'chartLabels' => $bucket['labels'],
            'chartInvoiced' => $bucket['invoiced'],
            'chartCollected' => $bucket['collected'],
            'jobMargins' => $this->jobMargins($currency),
            'taxEnabled' => (bool) $this->company->taxSetting?->tax_enabled,
            'taxRecaps' => $this->taxRecaps($currency),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'reports-financial',
            'title' => 'Reports',
        ]);
    }

    private function companyPayments(): Builder
    {
        return Payment::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Verified]);
    }

    private function openInvoices(): Builder
    {
        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('type', InvoiceType::Invoice)
            ->whereIn('status', [
                InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial,
                InvoiceStatus::Issued, InvoiceStatus::Overdue,
            ]);
    }

    /**
     * Mirrors the equivalent pre-TallStackUI Filament JobMarginReport
     * widget's query/columns exactly (see FINALIZED-DECISIONS.md §3 for
     * the full margin definition), company-scoped and paginated here
     * rather than relying on Filament's own table pagination.
     */
    private function jobMargins(?string $currency)
    {
        $salesOrders = SalesOrder::query()
            ->where('company_id', $this->company->id)
            ->with('client')
            ->withSum(['invoices as sales_total' => fn (Builder $query) => $query->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)], 'total')
            ->withSum(['invoices as sales_tax_total' => fn (Builder $query) => $query->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)], 'tax_total')
            ->withSum('jobCostAllocations as allocated_cost', 'amount')
            ->orderByDesc('id')
            ->paginate(10, pageName: 'margin-page');

        $unallocated = $this->unallocatedPurchasingCosts($salesOrders->pluck('id'));

        return $salesOrders->through(function (SalesOrder $order) use ($currency, $unallocated) {
            $salesValue = round((float) $order->sales_total - (float) $order->sales_tax_total, 2);
            $allocatedCost = round((float) $order->allocated_cost, 2);

            return [
                'id' => $order->id,
                'number' => $order->number,
                'client' => $order->client?->name ?? '—',
                'status' => $order->status->getLabel(),
                'status_color' => StatusColor::map($order->status->getColor()),
                'sales_value' => Money::format($salesValue, $currency),
                'allocated_cost' => Money::format($allocatedCost, $currency),
                'margin' => Money::format(round($salesValue - $allocatedCost, 2), $currency),
                'margin_negative' => round($salesValue - $allocatedCost, 2) < 0,
                'unallocated_cost' => Money::format($unallocated->get($order->id, 0.0), $currency),
            ];
        });
    }

    /**
     * Ports JobMarginReport::loadUnallocatedPurchasingCosts() unmodified —
     * two small aggregate queries rather than one query per row — scoped
     * here to only the sales-order ids on the current page.
     *
     * @param  Collection<int, int>  $salesOrderIds
     * @return Collection<int, float> keyed by sales_order_id
     */
    private function unallocatedPurchasingCosts(Collection $salesOrderIds): Collection
    {
        if ($salesOrderIds->isEmpty()) {
            return collect();
        }

        $pairs = JobCostAllocation::query()
            ->whereIn('sales_order_id', $salesOrderIds)
            ->select('sales_order_id', 'vendor_bill_item_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $itemIds = $pairs->pluck('vendor_bill_item_id')->unique()->values();

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

        return $result;
    }

    /**
     * Real TaxRecap rows for this company, reached only through their
     * owning Invoice (TaxRecap carries no direct company_id). Status is
     * derived the same way App\Actions\Billing\FileOrAdjustTaxRecap and
     * InvoiceInfolist's own action already treat these columns — never a
     * new status value invented here.
     */
    private function taxRecaps(?string $currency)
    {
        return TaxRecap::query()
            ->whereHas('invoice', fn (Builder $query) => $query->where('company_id', $this->company->id))
            ->with('invoice.client')
            ->orderByDesc('id')
            ->paginate(10, pageName: 'tax-page')
            ->through(function (TaxRecap $recap) use ($currency) {
                $filed = filled($recap->filing_date) || $recap->manual_entry_status === 'filed';
                $adjusted = filled($recap->adjusted_at);

                return [
                    'id' => $recap->id,
                    'number' => $recap->number ?? '—',
                    'client' => $recap->invoice?->client?->name ?? '—',
                    'reporting_period' => $recap->reporting_period ?? '—',
                    'status_label' => $adjusted ? 'Adjusted' : ($filed ? 'Filed' : 'Pending'),
                    'status_color' => $adjusted ? 'blue' : ($filed ? 'green' : 'amber'),
                    'total' => Money::format((float) $recap->invoice?->tax_total, $currency),
                    'filing_date' => $recap->filing_date?->format('d M Y') ?? '—',
                    'pdf_url' => route('tax-recaps.pdf', $recap),
                ];
            });
    }
}
