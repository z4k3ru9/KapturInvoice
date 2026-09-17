<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Support\Dashboard\DashboardPeriod;
use App\Support\Dashboard\Money;
use App\Support\Dashboard\RevenueBuckets;
use App\Support\Dashboard\SetupChecklist;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Dashboard, built against the original Google Stitch shell/dashboard
 * mockup's visual-fidelity design — see CLAUDE.md's "Stitch UI remake"
 * section for the current, condensed shape. Reuses
 * DashboardPeriod/RevenueBuckets/SetupChecklist (App\Support\Dashboard)
 * unmodified. This page no longer renders its own "Action queue" card —
 * App\Support\Dashboard\ActionQueue is now surfaced only from the shell's
 * notification bell (resources/views/components/tallstack/app.blade.php),
 * reachable from every page including this one, so a second copy here
 * was redundant.
 *
 * Stats/chart lazy loading (beautification pass): `$statsLoaded` starts
 * `false` and the 5 stat cards / trend chart render as `skeleton` in that
 * state. `loadDashboardData()` — the actual `RevenueBuckets::forPeriod()`
 * work, a loop of up to ~2 queries per day/month bucket in the selected
 * period, genuinely the most expensive part of this page — is deliberately
 * NOT called from `mount()`/`render()`, so the first HTTP response paints
 * the skeleton immediately without waiting on it. The Blade view fires it
 * via `wire:init`, a second, separate Livewire round-trip the browser
 * makes right after the first paint — this is what makes the loading state
 * "genuine" (the initial response is measurably lighter) rather than a
 * `skeleton` prop with nothing behind it. The same method re-runs on every
 * period change (`updatedPeriod()`) and on the header's manual refresh
 * button, and a `wire:loading` skeleton overlay (no `wire:target`,
 * matching ANY request this component makes) covers those subsequent
 * round-trips too, so the loading state is visible on every re-render, not
 * only the first.
 */
#[Layout('components.tallstack.app')]
class TallStackDashboard extends Component
{
    public Company $company;

    public string $period = 'this_month';

    public bool $statsLoaded = false;

    /** @var array<string, mixed> */
    public array $stats = [];

    /** @var list<string> */
    public array $chartLabels = [];

    /** @var list<float> */
    public array $chartInvoiced = [];

    /** @var list<float> */
    public array $chartCollected = [];

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);
    }

    /**
     * Computes the stat cards and trend-chart buckets — see this class's
     * own docblock for why this is split out of `render()` rather than run
     * eagerly. Never called from `mount()`. Every call — the initial
     * wire:init load, a period change, or the auto-refresh interval (see
     * the Blade view's own x-init) — ends by dispatching a browser
     * 'dashboard-refreshed' event so the shell's floating status
     * indicator (components/tallstack/app.blade.php) can show when the
     * dashboard was last updated without this component needing to know
     * that indicator exists.
     */
    public function loadDashboardData(): void
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

        $this->stats = [
            'revenue' => Money::format($revenue, $currency),
            'revenueRaw' => $revenue,
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
        ];

        $bucket = RevenueBuckets::forPeriod($period);
        $this->chartLabels = $bucket['labels'];
        $this->chartInvoiced = $bucket['invoiced'];
        $this->chartCollected = $bucket['collected'];

        $this->statsLoaded = true;

        $this->dispatch('dashboard-refreshed');
    }

    /**
     * Downloads the current period's stat cards and revenue trend as a
     * plain CSV — the "Export summary" header button
     * (tallstack-dashboard.blade.php), previously decorative with no
     * `wire:click` at all. Loads the stats itself rather than trusting
     * `$statsLoaded`: the header renders before `wire:init` fires
     * `loadDashboardData()` on first paint, so a click in that brief
     * window would otherwise export an empty `$this->stats` array.
     * Returning a `streamDownload()` response directly from a component
     * method is Livewire's own documented file-download mechanism — no
     * separate controller/route needed, matching this being dashboard-only
     * export, not a reusable document type.
     */
    public function exportSummary(): mixed
    {
        if (! $this->statsLoaded) {
            $this->loadDashboardData();
        }

        $periodLabel = DashboardPeriod::resolve(['period' => $this->period])['label'];
        $stats = $this->stats;

        $rows = [
            ["KapturInvoice \u{2014} {$this->company->name}"],
            ["Dashboard summary \u{2014} {$periodLabel}"],
            [],
            ['Metric', 'Value'],
            ['Total revenue', $stats['revenue']],
            ['Outstanding balance', $stats['outstanding']],
            ['Outstanding invoices', $stats['outstandingCount']],
            ['Overdue invoices', $stats['overdueCount']],
            ['Overdue total', $stats['overdueTotal']],
            ['Open quotations', $stats['openQuotations']],
            ['Active jobs', $stats['activeJobs']],
            [],
            ['Revenue & cash inflow trend'],
            ['Period', 'Invoiced (legacy)', 'Cash collected'],
            ...collect($this->chartLabels)->map(fn (string $label, int $index) => [
                $label,
                Money::format($this->chartInvoiced[$index] ?? 0.0, $this->company->currency_code),
                Money::format($this->chartCollected[$index] ?? 0.0, $this->company->currency_code),
            ])->all(),
        ];

        $filename = Str::slug($this->company->name).'-dashboard-summary-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row, escape: '\\');
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Livewire lifecycle hook: reloads the stats/chart whenever the period
     * dropdown changes `$this->period` (via `$set`), so switching periods
     * shows fresh numbers rather than stale ones from the previous load.
     */
    public function updatedPeriod(): void
    {
        $this->loadDashboardData();
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;
        $periodLabel = DashboardPeriod::resolve(['period' => $this->period])['label'];

        // Phase 12 (onboarding/zero-state) — reuses
        // App\Support\Dashboard\SetupChecklist unmodified (same "what
        // counts as done" logic as the pre-TallStackUI Filament setup
        // checklist widget), this is a presentation-layer port only. The
        // panel hides itself once every step is done, exactly mirroring
        // that widget's own canView() rule.
        $checklist = SetupChecklist::for($this->company);
        $firstIncomplete = collect($checklist['steps'])->search(fn (array $step) => ! $step['done']);
        $clientStep = collect($checklist['steps'])->firstWhere('key', 'client');
        $quotationStep = collect($checklist['steps'])->firstWhere('key', 'quotation');

        return view('livewire.tallstack-dashboard', [
            'periods' => DashboardPeriod::PERIODS,
            'periodLabel' => $periodLabel,
            'currency' => $currency,
            'refreshSeconds' => $this->company->dashboard_refresh_seconds,
            'statsLoaded' => $this->statsLoaded,
            'stats' => $this->stats,
            'chartLabels' => $this->chartLabels,
            'chartInvoiced' => $this->chartInvoiced,
            'chartCollected' => $this->chartCollected,
            // The chart's accessible-table alternative (the "View as
            // accessible table" toggle below the chart) — same buckets, one
            // row per label, formatted the same way as the stat cards so
            // the two representations never disagree.
            'chartRows' => collect($this->chartLabels)->map(fn (string $label, int $index) => [
                'period' => $label,
                'invoiced' => Money::format($this->chartInvoiced[$index] ?? 0.0, $currency),
                'collected' => Money::format($this->chartCollected[$index] ?? 0.0, $currency),
            ])->values(),
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
            'checklist' => $checklist,
            'showChecklist' => $checklist['done'] < $checklist['total'],
            'firstIncompleteStep' => $firstIncomplete === false ? null : $firstIncomplete,
            'isZeroState' => ! $clientStep['done'] && ! $quotationStep['done'],
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
