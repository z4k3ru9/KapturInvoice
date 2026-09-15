<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Support\ActionQueue;
use App\Filament\Support\DashboardPeriod;
use App\Filament\Support\Money;
use App\Filament\Support\RevenueBuckets;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the same Dashboard — built to compare against
 * App\Filament\Pages\Dashboard's Filament-widget version for visual
 * fidelity against the Stitch "Dashboard - Company Overview" mockup (see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md).
 * Deliberately reuses the exact same domain logic as the Filament
 * widgets (DashboardPeriod, RevenueBuckets, ActionQueue,
 * App\Filament\Widgets\RevenueOverview's own stat queries) rather than
 * recomputing anything, so the two presentations never disagree about
 * what a number means.
 */
#[Layout('components.tallstack.app')]
class TallStackDashboard extends Component
{
    public Company $company;

    public string $period = 'this_month';

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // ActionQueue::for() builds its item links via Filament resource
        // URL generation (Resource::getUrl()), which needs a current
        // panel + tenant even though this page itself is a plain
        // Livewire route outside the panel — set both manually so those
        // links resolve instead of throwing.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);
    }

    public function render(): View
    {
        $period = DashboardPeriod::resolve(['period' => $this->period]);
        $previous = DashboardPeriod::previous($period);
        $currency = $this->company->currency_code;

        $revenue = $this->companyPayments()
            ->whereBetween('payment_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount');
        $previousRevenue = $this->companyPayments()
            ->whereBetween('payment_date', [$previous['start']->toDateString(), $previous['end']->toDateString()])
            ->sum('amount');

        $outstanding = $this->openInvoices();
        $overdue = $this->openInvoices()->where('due_date', '<', today());

        $bucket = RevenueBuckets::forPeriod($period);

        return view('livewire.tallstack-dashboard', [
            'periods' => DashboardPeriod::PERIODS,
            'periodLabel' => $period['label'],
            'currency' => $currency,
            'stats' => [
                'revenue' => Money::format($revenue, $currency),
                'revenueUp' => $revenue >= $previousRevenue,
                'revenueFlat' => $revenue == 0.0 && $previousRevenue == 0.0,
                'outstanding' => Money::format((clone $outstanding)->sum('balance'), $currency),
                'outstandingCount' => (clone $outstanding)->count(),
                'overdueCount' => (clone $overdue)->count(),
                'overdueTotal' => Money::format((clone $overdue)->sum('balance'), $currency),
                'openQuotations' => Quotation::query()
                    ->where('company_id', $this->company->id)
                    ->whereIn('status', [QuotationStatus::Approved, QuotationStatus::Sent])
                    ->count(),
                'activeJobs' => SalesOrder::query()
                    ->where('company_id', $this->company->id)
                    ->whereIn('status', [
                        SalesOrderStatus::Approved, SalesOrderStatus::Procurement,
                        SalesOrderStatus::InProgress, SalesOrderStatus::Delivered, SalesOrderStatus::HandedOver,
                    ])
                    ->count(),
            ],
            'chartLabels' => $bucket['labels'],
            'chartInvoiced' => $bucket['invoiced'],
            'chartCollected' => $bucket['collected'],
            'expiring' => Quotation::query()
                ->where('company_id', $this->company->id)
                ->where('status', QuotationStatus::Sent)
                ->whereNotNull('valid_until')
                ->where('valid_until', '<=', now()->addDays(7))
                ->with('client')
                ->orderBy('valid_until')
                ->get()
                ->map(fn (Quotation $quotation) => [
                    'number' => $quotation->number,
                    'client' => $quotation->client?->name ?? '—',
                    'total' => Money::format((float) $quotation->total, $currency),
                    'days' => (int) floor(abs(now()->diffInDays($quotation->valid_until))),
                    'expired' => (bool) $quotation->valid_until?->isPast(),
                    'id' => $quotation->id,
                ]),
            'actionQueue' => ActionQueue::for(auth()->user(), $this->company),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'dashboard',
            'title' => 'Dashboard',
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
}
