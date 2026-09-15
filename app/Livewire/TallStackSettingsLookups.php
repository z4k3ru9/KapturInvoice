<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Models\ExpenseCategory;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Settings — Tax Rates & Small Lookups" screen — one
 * tabbed page for the three small company-scoped lookup tables
 * (App\Filament\Resources\TaxRates/ExpenseCategories/TaskStatuses), per
 * the fetched Stitch mockup of the same title: "design ONE representative
 * screen... rather than three near-duplicate mockups"
 * (docs/rebuild/outputs/26-stitch-missing-screens-prompts.md prompt 13).
 * Reuses each model's real Fillable field set exactly — nothing invented
 * (e.g. no color column on task_statuses, so no swatch is rendered even
 * though the mockup sketches one; see the view's own comment).
 *
 * "Delete disabled with tooltip... when referenced" (prompt 13) is new
 * presentational logic — no domain service computes "is this tax rate in
 * use" today, so isTaxRateInUse() below is a plain read-only existence
 * check across every real tax_rate_id/default_tax_rate_id/
 * default_tax_rate_1_id/default_tax_rate_2_id reference, kept local to
 * this component rather than a shared service since nothing else needs
 * it yet. It never changes what a delete actually does — TaxRate::delete()
 * remains exactly what TaxRatesTable's own DeleteBulkAction already calls.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsLookups extends Component
{
    use Interactions;

    public Company $company;

    public string $tab = 'tax-rates';

    public bool $taxEnabled = false;

    // --- Tax Rate modal state — TaxRateForm's own field set. -------------
    public bool $showTaxRateModal = false;

    public ?int $editingTaxRateId = null;

    public ?string $tr_name = null;

    public ?float $tr_rate = null;

    public bool $tr_is_inclusive = false;

    public ?string $tr_legacy_tax_rate_id = null;

    // --- Expense Category modal state -------------------------------------
    public bool $showExpenseCategoryModal = false;

    public ?int $editingExpenseCategoryId = null;

    public ?string $ec_name = null;

    // --- Task Status modal state ------------------------------------------
    public bool $showTaskStatusModal = false;

    public ?int $editingTaskStatusId = null;

    public ?string $ts_name = null;

    public ?int $ts_sort_order = 0;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $this->taxEnabled = (bool) CompanyTaxSetting::query()
            ->where('company_id', $company->id)
            ->value('tax_enabled');
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
    }

    // --- Tax Rates ---------------------------------------------------------

    public function openCreateTaxRateModal(): void
    {
        $this->authorize('create', TaxRate::class);

        $this->resetTaxRateForm();
        $this->showTaxRateModal = true;
    }

    public function openEditTaxRateModal(int $id): void
    {
        $taxRate = $this->scopedTaxRate($id);

        if (! $taxRate) {
            return;
        }

        $this->authorize('update', $taxRate);

        $this->editingTaxRateId = $taxRate->id;
        $this->tr_name = $taxRate->name;
        $this->tr_rate = (float) $taxRate->rate;
        $this->tr_is_inclusive = (bool) $taxRate->is_inclusive;
        $this->tr_legacy_tax_rate_id = $taxRate->legacy_tax_rate_id !== null ? (string) $taxRate->legacy_tax_rate_id : null;
        $this->showTaxRateModal = true;
    }

    public function saveTaxRate(): void
    {
        $data = $this->validate([
            'tr_name' => ['required', 'string', 'max:255'],
            'tr_rate' => ['required', 'numeric', 'min:0'],
            'tr_is_inclusive' => ['boolean'],
            'tr_legacy_tax_rate_id' => ['nullable', 'numeric'],
        ]);

        $payload = [
            'name' => $data['tr_name'],
            'rate' => $data['tr_rate'],
            'is_inclusive' => $data['tr_is_inclusive'],
            'legacy_tax_rate_id' => filled($data['tr_legacy_tax_rate_id']) ? $data['tr_legacy_tax_rate_id'] : null,
        ];

        if ($this->editingTaxRateId) {
            $taxRate = $this->scopedTaxRate($this->editingTaxRateId);

            if (! $taxRate) {
                return;
            }

            $this->authorize('update', $taxRate);
            $taxRate->update($payload);
        } else {
            $this->authorize('create', TaxRate::class);

            $payload['company_id'] = $this->company->id;
            TaxRate::create($payload);
        }

        $this->showTaxRateModal = false;
        $this->resetTaxRateForm();
        $this->toast()->success('Tax rate saved.')->send();
    }

    public function deleteTaxRate(int $id): void
    {
        $taxRate = $this->scopedTaxRate($id);

        if (! $taxRate) {
            return;
        }

        $this->authorize('delete', $taxRate);

        if ($this->taxRateUsageCount($taxRate) > 0) {
            $this->toast()->error('Cannot delete', 'This tax rate is still in use.')->send();

            return;
        }

        $taxRate->delete();
        $this->toast()->success('Tax rate removed.')->send();
    }

    private function resetTaxRateForm(): void
    {
        $this->editingTaxRateId = null;
        $this->tr_name = null;
        $this->tr_rate = null;
        $this->tr_is_inclusive = false;
        $this->tr_legacy_tax_rate_id = null;
        $this->resetErrorBag();
    }

    private function scopedTaxRate(int $id): ?TaxRate
    {
        return TaxRate::query()->where('company_id', $this->company->id)->find($id);
    }

    /**
     * How many real records reference this tax rate — the union of every
     * `tax_rate_id`/`default_tax_rate_id`/`default_tax_rate_1_id`/
     * `default_tax_rate_2_id` foreign key in the schema (see the table's
     * own docblock above). A plain read-only count, not a cached/derived
     * column.
     */
    private function taxRateUsageCount(TaxRate $taxRate): int
    {
        return DB::table('invoice_item_taxes')->where('tax_rate_id', $taxRate->id)->count()
            + DB::table('expense_taxes')->where('tax_rate_id', $taxRate->id)->count()
            + DB::table('products')->where('default_tax_rate_id', $taxRate->id)->count()
            + DB::table('companies')->where('default_tax_rate_1_id', $taxRate->id)->orWhere('default_tax_rate_2_id', $taxRate->id)->count();
    }

    // --- Expense Categories -------------------------------------------------

    public function openCreateExpenseCategoryModal(): void
    {
        $this->authorize('create', ExpenseCategory::class);

        $this->editingExpenseCategoryId = null;
        $this->ec_name = null;
        $this->resetErrorBag();
        $this->showExpenseCategoryModal = true;
    }

    public function openEditExpenseCategoryModal(int $id): void
    {
        $category = ExpenseCategory::query()->where('company_id', $this->company->id)->find($id);

        if (! $category) {
            return;
        }

        $this->authorize('update', $category);

        $this->editingExpenseCategoryId = $category->id;
        $this->ec_name = $category->name;
        $this->showExpenseCategoryModal = true;
    }

    public function saveExpenseCategory(): void
    {
        $data = $this->validate(['ec_name' => ['required', 'string', 'max:255']]);

        if ($this->editingExpenseCategoryId) {
            $category = ExpenseCategory::query()->where('company_id', $this->company->id)->find($this->editingExpenseCategoryId);

            if (! $category) {
                return;
            }

            $this->authorize('update', $category);
            $category->update(['name' => $data['ec_name']]);
        } else {
            $this->authorize('create', ExpenseCategory::class);
            ExpenseCategory::create(['company_id' => $this->company->id, 'name' => $data['ec_name']]);
        }

        $this->showExpenseCategoryModal = false;
        $this->toast()->success('Expense category saved.')->send();
    }

    public function deleteExpenseCategory(int $id): void
    {
        $category = ExpenseCategory::query()->where('company_id', $this->company->id)->find($id);

        if (! $category) {
            return;
        }

        $this->authorize('delete', $category);
        $category->delete();
        $this->toast()->success('Expense category removed.')->send();
    }

    // --- Task Statuses --------------------------------------------------------

    public function openCreateTaskStatusModal(): void
    {
        $this->authorize('create', TaskStatus::class);

        $this->editingTaskStatusId = null;
        $this->ts_name = null;
        $this->ts_sort_order = ((int) TaskStatus::query()->where('company_id', $this->company->id)->max('sort_order')) + 1;
        $this->resetErrorBag();
        $this->showTaskStatusModal = true;
    }

    public function openEditTaskStatusModal(int $id): void
    {
        $status = TaskStatus::query()->where('company_id', $this->company->id)->find($id);

        if (! $status) {
            return;
        }

        $this->authorize('update', $status);

        $this->editingTaskStatusId = $status->id;
        $this->ts_name = $status->name;
        $this->ts_sort_order = $status->sort_order;
        $this->showTaskStatusModal = true;
    }

    public function saveTaskStatus(): void
    {
        $data = $this->validate([
            'ts_name' => ['required', 'string', 'max:255'],
            'ts_sort_order' => ['nullable', 'integer'],
        ]);

        $payload = ['name' => $data['ts_name'], 'sort_order' => $data['ts_sort_order'] ?? 0];

        if ($this->editingTaskStatusId) {
            $status = TaskStatus::query()->where('company_id', $this->company->id)->find($this->editingTaskStatusId);

            if (! $status) {
                return;
            }

            $this->authorize('update', $status);
            $status->update($payload);
        } else {
            $this->authorize('create', TaskStatus::class);

            $payload['company_id'] = $this->company->id;
            TaskStatus::create($payload);
        }

        $this->showTaskStatusModal = false;
        $this->toast()->success('Task status saved.')->send();
    }

    public function deleteTaskStatus(int $id): void
    {
        $status = TaskStatus::query()->where('company_id', $this->company->id)->find($id);

        if (! $status) {
            return;
        }

        $this->authorize('delete', $status);
        $status->delete();
        $this->toast()->success('Task status removed.')->send();
    }

    public function render(): View
    {
        $taxRates = collect();

        if ($this->taxEnabled) {
            $taxRates = TaxRate::query()
                ->where('company_id', $this->company->id)
                ->orderBy('name')
                ->get()
                ->map(fn (TaxRate $taxRate) => [
                    'id' => $taxRate->id,
                    'name' => $taxRate->name,
                    'rate' => number_format((float) $taxRate->rate, 3).'%',
                    'is_inclusive' => (bool) $taxRate->is_inclusive,
                    'usage' => $this->taxRateUsageCount($taxRate),
                ]);
        }

        $expenseCategories = ExpenseCategory::query()
            ->where('company_id', $this->company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taskStatuses = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        return view('livewire.tallstack-settings-lookups', [
            'taxRates' => $taxRates,
            'expenseCategories' => $expenseCategories,
            'taskStatuses' => $taskStatuses,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'settings-lookups',
            'title' => 'Tax Rates & Small Lookups',
        ]);
    }
}
