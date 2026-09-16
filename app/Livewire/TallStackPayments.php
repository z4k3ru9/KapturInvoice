<?php

namespace App\Livewire;

use App\Actions\Receivables\ForceDeletePayment;
use App\Actions\Receivables\IssuePaymentReceipt;
use App\Actions\Receivables\RecordCustomerPayment;
use App\Actions\Receivables\ReverseCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Services\BillingMailer;
use App\Support\Dashboard\Money;
use App\Support\Files\EncryptedFileStorage;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Payments register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Payment,
 * App\Enums\PaymentStatus, and every App\Actions\Receivables\* action the
 * equivalent pre-TallStackUI Filament payments table already used for
 * record/verify/issue-receipt/reverse — this is a presentation-layer swap
 * only, never a reimplementation of the payment lifecycle.
 *
 * Allocate/Amend allocation are deliberately NOT built here — they need
 * the richer per-invoice repeater UI, which lives on the sibling detail
 * page App\Livewire\TallStackPaymentAllocation instead (matching the
 * "Payments & Receipts - Allocation Panel" Stitch mockup, a dedicated
 * page rather than a list-row modal).
 */
#[Layout('components.tallstack.app')]
class TallStackPayments extends Component
{
    use Interactions, WithFileUploads, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'payment_date', 'direction' => 'desc'];

    public int $quantity = 10;

    // --- Record payment modal --------------------------------------------
    public bool $showRecordModal = false;

    public ?string $record_client_id = null;

    public ?string $record_method = null;

    public float $record_amount = 0;

    public ?string $record_payment_date = null;

    public ?string $record_reference = null;

    public ?string $record_notes = null;

    /** @var TemporaryUploadedFile|null */
    public $record_proof = null;

    // --- Verify modal ------------------------------------------------------
    public ?int $verifyingId = null;

    public bool $showVerifyModal = false;

    public ?string $verify_cheque_cleared_at = null;

    // --- Reverse modal -------------------------------------------------------
    public ?int $reversingId = null;

    public bool $showReverseModal = false;

    public ?string $reverse_reason = null;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackDashboard::mount() — needed for any
        // future Resource::getUrl() usage, kept for parity with the rest
        // of the TALL-stack pages.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterStatus(?string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    // --- Record payment ------------------------------------------------------

    public function openRecordModal(): void
    {
        $this->authorize('create', Payment::class);

        $this->reset([
            'record_client_id', 'record_method', 'record_amount',
            'record_payment_date', 'record_reference', 'record_notes', 'record_proof',
        ]);
        $this->record_payment_date = now()->toDateString();
        $this->showRecordModal = true;
    }

    public function recordPayment(): void
    {
        $this->authorize('create', Payment::class);

        $data = $this->validate([
            'record_client_id' => 'required|exists:clients,id',
            'record_method' => 'required|in:'.implode(',', array_map(fn ($c) => $c->value, PaymentMethod::cases())),
            'record_amount' => 'required|numeric|min:0.01',
            'record_payment_date' => 'required|date',
            'record_reference' => 'nullable|string|max:255',
            'record_notes' => 'nullable|string',
            'record_proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $client = Client::query()->where('company_id', $this->company->id)->find($data['record_client_id']);

        if (! $client) {
            $this->toast()->error('Could not record payment', 'That client was not found.')->send();

            return;
        }

        try {
            $proofPath = app(EncryptedFileStorage::class)->store($this->record_proof, 'payment-proofs');

            app(RecordCustomerPayment::class)->record([
                'company_id' => $this->company->id,
                'client_id' => $client->id,
                'method' => PaymentMethod::from($data['record_method']),
                'amount' => $data['record_amount'],
                'currency_code' => $this->company->currency_code,
                'payment_date' => $data['record_payment_date'],
                'proof_path' => $proofPath,
                'reference' => $data['record_reference'],
                'notes' => $data['record_notes'],
            ]);

            $this->showRecordModal = false;
            $this->toast()->success('Payment recorded.', 'Verify it, then allocate it to invoices.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not record payment', $e->getMessage())->send();
        }
    }

    // --- Verify ----------------------------------------------------------

    public function openVerifyModal(int $id): void
    {
        $this->verifyingId = $id;
        $this->verify_cheque_cleared_at = null;
        $this->showVerifyModal = true;
    }

    public function verify(): void
    {
        $payment = $this->findScoped($this->verifyingId);

        if (! $payment) {
            return;
        }

        try {
            app(VerifyCustomerPayment::class)->verify(
                $payment,
                auth()->user(),
                filled($this->verify_cheque_cleared_at) ? Carbon::parse($this->verify_cheque_cleared_at) : null,
            );

            $this->showVerifyModal = false;
            $this->toast()->success('Payment verified.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not verify payment', $e->getMessage())->send();
        }
    }

    // --- Issue receipt -----------------------------------------------------

    public function issueReceipt(int $id): void
    {
        $payment = $this->findScoped($id);

        if (! $payment) {
            return;
        }

        try {
            $receipt = app(IssuePaymentReceipt::class)->issue($payment);
            $this->toast()->success('Receipt issued.', "Receipt #{$receipt->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not issue receipt', $e->getMessage())->send();
        }
    }

    /**
     * Reconnects App\Services\BillingMailer::sendPaymentReceipt() to the
     * UI — it existed but had no caller anywhere in the TallStackUI
     * rebuild (flagged in CLAUDE.md's "Conventions" section). Mirrors
     * TallStackInvoiceForm::send()'s try/catch shape, without a CC modal
     * since a receipt resend has no established need for one yet.
     */
    public function sendReceipt(int $id): void
    {
        $payment = $this->findScoped($id);

        if (! $payment || ! $payment->receipt) {
            return;
        }

        try {
            app(BillingMailer::class)->sendPaymentReceipt($payment);
            $this->toast()->success('Receipt sent.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not send receipt', $e->getMessage())->send();
        }
    }

    // --- Reverse -----------------------------------------------------------

    public function openReverseModal(int $id): void
    {
        $this->reversingId = $id;
        $this->reverse_reason = null;
        $this->showReverseModal = true;
    }

    public function reverse(): void
    {
        $payment = $this->findScoped($this->reversingId);

        if (! $payment) {
            return;
        }

        $data = $this->validate([
            'reverse_reason' => 'required|string',
        ]);

        try {
            app(ReverseCustomerPayment::class)->reverse($payment, $data['reverse_reason'], auth()->user());

            $this->showReverseModal = false;
            $this->toast()->success('Payment reversed.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not reverse payment', $e->getMessage())->send();
        }
    }

    // --- Force delete --------------------------------------------------------

    public function forceDelete(int $id): void
    {
        $payment = $this->findScoped($id);

        if (! $payment) {
            return;
        }

        try {
            app(ForceDeletePayment::class)->forceDelete($payment, auth()->user());
            $this->toast()->success('Payment permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete payment', $e->getMessage())->send();
        }
    }

    /** Never trust a bare `Payment::find()` here — always re-check company ownership, the same explicit guard the PDF controllers use since this route sits outside Filament's own tenant-scoped binding. */
    private function findScoped(?int $id): ?Payment
    {
        if (! $id) {
            return null;
        }

        $payment = Payment::find($id);

        if (! $payment || $payment->company_id !== $this->company->id) {
            return null;
        }

        return $payment;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Payment::query()->where('payments.company_id', $this->company->id);

        $payments = (clone $base)
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->select('payments.*')
            ->with(['client', 'invoice', 'receipt'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('invoice', fn ($q) => $q->where('number', 'like', "%{$this->search}%"));
            }))
            ->when($this->sort['column'] === 'client',
                fn ($q) => $q->orderBy('clients.name', $this->sort['direction']),
                fn ($q) => $q->orderBy('payments.'.$this->sort['column'], $this->sort['direction'])
            )
            ->paginate($this->quantity)
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'client' => $payment->client?->name ?? '—',
                'invoice' => $payment->invoice?->number ?? '—',
                'amount' => Money::format((float) $payment->amount, $currency),
                'method' => $payment->method ? str_replace('_', ' ', ucfirst($payment->method)) : '—',
                'method_raw' => $payment->method,
                'status' => $payment->status,
                'status_label' => $payment->status->getLabel(),
                'status_color' => StatusColor::map($payment->status->getColor()),
                'has_receipt' => $payment->receipt !== null,
                'receipt_id' => $payment->receipt?->id,
                'receipt_number' => $payment->receipt?->number,
                'payment_date' => $payment->payment_date?->format('d M Y') ?? '—',
            ]);

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate, sum(amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $pendingCount = (int) ($counts[PaymentStatus::Pending->value]->aggregate ?? 0);
        $verifiedTotal = (float) ($counts[PaymentStatus::Verified->value]->total ?? 0);
        $receiptsIssued = (clone $base)->whereHas('receipt')->count();
        $unallocatedCount = (clone $base)
            ->where('status', PaymentStatus::Verified)
            ->whereDoesntHave('allocations', fn ($q) => $q->where('is_active', true))
            ->count();

        return view('livewire.tallstack-payments', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'pendingCount' => $pendingCount,
                'verifiedTotal' => Money::format($verifiedTotal, $currency),
                'receiptsIssued' => $receiptsIssued,
                'unallocatedCount' => $unallocatedCount,
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'payments',
            'title' => 'Payments',
        ]);
    }
}
