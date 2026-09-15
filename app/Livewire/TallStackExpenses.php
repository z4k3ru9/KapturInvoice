<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesDocuments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Services\ExpenseTotalsCalculator;
use App\Support\Dashboard\Money;
use App\Support\Html\RichTextSanitizer;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Expenses register — see App\Livewire\TallStackClients's docblock for
 * the established pattern this follows. Reuses App\Models\Expense and
 * App\Services\ExpenseTotalsCalculator unmodified — this is a
 * presentation-layer swap only, mirroring the equivalent pre-TallStackUI
 * Filament expense resource's form and table field-for-field: no field is
 * added or dropped, `tax_rate_ids` stays a virtual field synced through
 * ExpenseTotalsCalculator::syncTaxes() exactly like that resource's own
 * Create/Edit pages did.
 *
 * Unlike the Filament resource (which kept full Create/Edit/View pages),
 * this page follows this app's small-resource TALL-stack convention (see
 * TallStackClients/TallStackVendors) with an in-page create/edit modal
 * rather than separate routes — per the build prompt's own instruction to
 * match "Expense's small-resource shape", and matching the fetched Stitch
 * mockup ("Expenses Register & Edit Modal (Karunia Abadi)") which only
 * ever shows a modal, never a standalone detail page.
 */
#[Layout('components.tallstack.app')]
class TallStackExpenses extends Component
{
    use Interactions, ManagesDocuments, WithFileUploads, WithPagination;

    public Company $company;

    public string $search = '';

    public string $categoryFilter = '';

    // --- Create/edit modal state — exactly ExpenseForm's own field set,
    // plus exchange_rate, which is a real `expenses` column
    // (App\Models\Expense's #[Fillable] list) the Filament form itself
    // never surfaces but the build prompt explicitly asks for. ---
    public bool $showExpenseModal = false;

    public ?int $editingExpenseId = null;

    public ?int $vendor_id = null;

    public ?int $expense_category_id = null;

    public ?int $client_id = null;

    public ?int $invoice_id = null;

    public ?string $expense_date = null;

    public ?string $currency_code = null;

    public float $exchange_rate = 1.0;

    public float $subtotal = 0;

    /** @var array<int> */
    public array $tax_rate_ids = [];

    public bool $should_be_invoiced = false;

    public ?string $transaction_reference = null;

    public ?string $private_notes = null;

    // Read-only, recomputed display only — never dehydrated back into the
    // save() payload, same as ExpenseForm's disabled/dehydrated(false)
    // tax_total/total fields.
    public float $tax_total = 0;

    public float $total = 0;

    // Document attachment state — see App\Livewire\Concerns\ManagesDocuments.
    // Only reachable while editing an existing expense (a Document needs a
    // real documentable row to attach to), same restriction the Invoice/
    // Quotation forms apply.
    /** @var TemporaryUploadedFile|null */
    public $newDocument = null;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackClients::mount() — the create/update/
        // delete Gate::before in AppServiceProvider::registerCompanyRoleGate()
        // reads the active Filament tenant rather than a route parameter.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showExpenseModal = true;
    }

    public function openEditModal(int $id): void
    {
        $expense = $this->findScoped($id);

        if (! $expense) {
            return;
        }

        $this->editingExpenseId = $expense->id;
        $this->vendor_id = $expense->vendor_id;
        $this->expense_category_id = $expense->expense_category_id;
        $this->client_id = $expense->client_id;
        $this->invoice_id = $expense->invoice_id;
        $this->expense_date = $expense->expense_date?->toDateString();
        $this->currency_code = $expense->currency_code ?? $this->company->currency_code;
        $this->exchange_rate = (float) $expense->exchange_rate;
        $this->subtotal = (float) $expense->subtotal;
        $this->tax_rate_ids = $expense->taxes()->pluck('tax_rate_id')->filter()->values()->all();
        $this->should_be_invoiced = (bool) $expense->should_be_invoiced;
        $this->transaction_reference = $expense->transaction_reference;
        $this->private_notes = $expense->private_notes;
        $this->tax_total = (float) $expense->tax_total;
        $this->total = (float) $expense->total;
        $this->showExpenseModal = true;
    }

    public function save(): void
    {
        $this->private_notes = app(RichTextSanitizer::class)->sanitize($this->private_notes);

        $data = $this->validate([
            'vendor_id' => ['nullable', 'integer'],
            'expense_category_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'expense_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'exchange_rate' => ['numeric', 'min:0'],
            'subtotal' => ['numeric', 'min:0'],
            'tax_rate_ids' => ['array'],
            'tax_rate_ids.*' => ['integer'],
            'should_be_invoiced' => ['boolean'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'private_notes' => ['nullable', 'string'],
        ]);

        $taxRateIds = Arr::pull($data, 'tax_rate_ids', []);

        // "should_be_invoiced" gates whether client_id/invoice_id are kept
        // — same as ExpenseForm's ->visible(fn (Get $get) => ...) on those
        // two fields, just enforced server-side since a Livewire toggle
        // doesn't remove the underlying property value on its own.
        if (! $data['should_be_invoiced']) {
            $data['client_id'] = null;
            $data['invoice_id'] = null;
        }

        if ($this->editingExpenseId) {
            $expense = $this->findScoped($this->editingExpenseId);

            if (! $expense) {
                return;
            }

            $expense->update($data);
            app(ExpenseTotalsCalculator::class)->syncTaxes($expense, $taxRateIds);
            app(ExpenseTotalsCalculator::class)->recalculate($expense);
            $this->toast()->success('Expense saved.')->send();
        } else {
            $data['company_id'] = $this->company->id;
            $expense = Expense::create($data);
            app(ExpenseTotalsCalculator::class)->syncTaxes($expense, $taxRateIds);
            app(ExpenseTotalsCalculator::class)->recalculate($expense);
            $this->toast()->success('Expense created.')->send();
        }

        $this->showExpenseModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $expense = $this->findScoped($id);

        if (! $expense) {
            return;
        }

        $expense->delete();
        $this->toast()->success('Expense deleted.')->send();
    }

    private function resetForm(): void
    {
        $this->editingExpenseId = null;
        $this->vendor_id = null;
        $this->expense_category_id = null;
        $this->client_id = null;
        $this->invoice_id = null;
        $this->expense_date = null;
        $this->currency_code = $this->company->currency_code;
        $this->exchange_rate = 1.0;
        $this->subtotal = 0;
        $this->tax_rate_ids = [];
        $this->should_be_invoiced = false;
        $this->transaction_reference = null;
        $this->private_notes = null;
        $this->tax_total = 0;
        $this->total = 0;
        $this->newDocument = null;
    }

    // --- Documents ---------------------------------------------------------

    public function uploadDocument(): void
    {
        $expense = $this->findScoped($this->editingExpenseId);

        if (! $expense) {
            return;
        }

        $this->authorize('update', $expense);

        $this->validate($this->documentUploadRules('newDocument'));

        $this->storeUploadedDocument($expense, $this->newDocument);

        $this->reset('newDocument');
        $this->toast()->success('Document uploaded.')->send();
    }

    public function deleteDocument(int $id): void
    {
        $expense = $this->findScoped($this->editingExpenseId);

        if (! $expense) {
            return;
        }

        $this->authorize('update', $expense);

        if ($this->deleteScopedDocument($expense, $id)) {
            $this->toast()->success('Document deleted.')->send();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    public function editingExpenseDocuments()
    {
        $expense = $this->findScoped($this->editingExpenseId);

        return $expense ? $this->documentRows($expense) : collect();
    }

    /** Never trust a bare `Expense::find()` — always re-check company ownership explicitly, same guard every other TALL-stack page uses. */
    private function findScoped(?int $id): ?Expense
    {
        if (! $id) {
            return null;
        }

        $expense = Expense::find($id);

        if (! $expense || $expense->company_id !== $this->company->id) {
            return null;
        }

        return $expense;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $expenses = Expense::query()
            ->where('company_id', $this->company->id)
            ->with(['vendor', 'category', 'client'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('transaction_reference', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->categoryFilter, fn ($q) => $q->where('expense_category_id', $this->categoryFilter))
            ->orderByDesc('expense_date')
            ->paginate(10)
            ->through(fn (Expense $expense) => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date?->format('d M Y') ?? '—',
                'vendor' => $expense->vendor?->name ?? '—',
                'category' => $expense->category?->name ?? '—',
                'client' => $expense->client?->name,
                'client_id' => $expense->client_id,
                'subtotal' => (float) $expense->subtotal,
                'subtotal_formatted' => Money::format((float) $expense->subtotal, $expense->currency_code ?: $currency),
                'should_be_invoiced' => (bool) $expense->should_be_invoiced,
                'transaction_reference' => $expense->transaction_reference ?? '—',
            ]);

        // Related-invoice options are scoped to the currently selected
        // client — mirrors ExpenseForm's Select::make('invoice_id')
        // ->relationship('invoice', 'number'), just filtered client-side
        // here since there's no per-select query callback in a plain
        // Livewire property.
        $invoiceOptions = $this->client_id
            ? Invoice::query()
                ->where('company_id', $this->company->id)
                ->where('client_id', $this->client_id)
                ->orderByDesc('invoice_date')
                ->pluck('number', 'id')
            : collect();

        return view('livewire.tallstack-expenses', [
            'expenses' => $expenses,
            'vendors' => Vendor::query()->where('company_id', $this->company->id)->orderBy('name')->pluck('name', 'id'),
            'categories' => ExpenseCategory::query()->where('company_id', $this->company->id)->orderBy('name')->pluck('name', 'id'),
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->pluck('name', 'id'),
            'invoiceOptions' => $invoiceOptions,
            'taxRates' => TaxRate::query()->where('company_id', $this->company->id)->orderBy('name')->pluck('name', 'id'),
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
            'documents' => $this->editingExpenseId ? $this->editingExpenseDocuments() : collect(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'expenses',
            'title' => 'Expenses',
        ]);
    }
}
