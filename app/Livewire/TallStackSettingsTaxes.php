<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Services\PeriodLockService;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Taxes" settings screen — App\Models\CompanyTaxSetting's
 * own field set plus Period Lock, split out of the former "Company &
 * Taxes" mega-page (2026-09-17 Settings reorganization, see memory.md):
 * both are financial-integrity controls (how tax is calculated, when a
 * period can no longer be backdated into), distinct from Identity or
 * Documents & Numbering. Period Lock is deliberately NOT gated behind
 * `tax_enabled` — closing a period matters for bookkeeping integrity
 * regardless of whether PPN tax specifically applies to this company
 * (Company A is non-tax and still needs it).
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never" gate
 * every other TALL-stack Settings page uses, re-checked here in mount()
 * and again in save()/closePeriod()/reopenPeriod().
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsTaxes extends Component
{
    use Interactions;

    public Company $company;

    public bool $tax_enabled = false;

    public ?float $standard_tax_rate = null;

    public ?int $dpp_factor_numerator = null;

    public ?int $dpp_factor_denominator = null;

    // --- Period lock — App\Services\PeriodLockService was fully built
    // (close/reopen, role-gated reopen with a required audited reason)
    // but had no caller anywhere in the app until it was wired up here;
    // same build-the-backend-first-wire-the-UI-later gap the
    // Hold/Release-hold mechanism had on Jobs. -----------------------
    public ?string $periodLockedThrough = null;

    public ?string $closeThroughDate = null;

    public bool $showReopenModal = false;

    public ?string $reopenReason = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $taxSetting = CompanyTaxSetting::query()->firstOrCreate(['company_id' => $company->id]);
        $this->tax_enabled = (bool) $taxSetting->tax_enabled;
        $this->standard_tax_rate = $taxSetting->standard_tax_rate !== null ? (float) $taxSetting->standard_tax_rate : null;
        $this->dpp_factor_numerator = $taxSetting->dpp_factor_numerator;
        $this->dpp_factor_denominator = $taxSetting->dpp_factor_denominator;

        $this->periodLockedThrough = $company->settings?->period_locked_through?->toDateString();
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'tax_enabled' => ['boolean'],
            'standard_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dpp_factor_numerator' => ['nullable', 'integer', 'min:0', 'max:255'],
            'dpp_factor_denominator' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        CompanyTaxSetting::query()->updateOrCreate(
            ['company_id' => $this->company->id],
            $data
        );

        $this->toast()->success('Settings saved.')->send();
    }

    // --- Period lock ---------------------------------------------------------

    /** Closing (moving the lock forward) is not itself destructive, so any settings-capable role may do it — App\Services\PeriodLockService's own docblock. */
    public function closePeriod(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate(['closeThroughDate' => ['required', 'date']]);

        app(PeriodLockService::class)->close($this->company, Carbon::parse($data['closeThroughDate']));

        $this->company->refresh();
        $this->periodLockedThrough = $this->company->settings?->period_locked_through?->toDateString();
        $this->closeThroughDate = null;

        $this->toast()->success('Period closed.', "Dates on or before {$this->periodLockedThrough} are now locked.")->send();
    }

    public function openReopenModal(): void
    {
        $this->authorize('viewSettings', $this->company);

        $this->reopenReason = null;
        $this->showReopenModal = true;
    }

    /** Reopening is restricted (Owner/Accountant, reason required, audited) — PeriodLockService::reopen() enforces the role check itself, this just surfaces its RuntimeException as a form error instead of a 500. */
    public function reopenPeriod(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate(['reopenReason' => ['required', 'string', 'max:1000']]);

        try {
            app(PeriodLockService::class)->reopen($this->company, Auth::user(), $data['reopenReason']);
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not reopen period', $e->getMessage())->send();

            return;
        }

        $this->company->refresh();
        $this->periodLockedThrough = null;
        $this->showReopenModal = false;
        $this->reopenReason = null;

        $this->toast()->success('Period reopened.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-taxes')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Taxes',
            ]);
    }
}
