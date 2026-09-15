<?php

namespace App\Livewire;

use App\Actions\Receivables\AllocateCustomerPayment;
use App\Actions\Receivables\AmendPaymentAllocation;
use App\Actions\Receivables\IssuePaymentReceipt;
use App\Actions\Receivables\ReverseCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack allocation panel for a single Payment — the sibling
 * detail page to App\Livewire\TallStackPayments, matching the "Payments &
 * Receipts - Allocation Panel (Axen Technology Variant)" Stitch mockup:
 * an incoming-payment summary, an allocated/remaining balance readout, and
 * a per-invoice allocation table for the payment's own client.
 *
 * Every mutation goes through the exact same App\Actions\Receivables\*
 * class the Filament PaymentsTable's row actions call — this page never
 * recomputes a balance or decides an allocation split on its own. The
 * allocation rows below are a dynamic add/remove list (mirroring
 * PaymentsTable's own Filament Repeater), including the same
 * duplicate-invoice-id summing behavior PaymentsTable::mapAllocations()
 * fixed (a real bug found and fixed on PR #4 — assigning instead of
 * summing silently dropped every row but the last for a repeated
 * invoice_id) — see mapAllocations() below, which mirrors that fix
 * exactly rather than reintroducing the bug in a second place.
 *
 * The Stitch mockup's "Auto-Allocate FIFO" and "Live escrow/credit
 * ledger" affordances have no corresponding domain action
 * (App\Actions\Receivables\* has no FIFO auto-split or escrow-balance
 * concept) — deliberately not built here rather than inventing new
 * financial behavior outside the approved action classes.
 */
#[Layout('components.tallstack.app')]
class TallStackPaymentAllocation extends Component
{
    use Interactions;

    public Company $company;

    public Payment $payment;

    /** @var array<int, array{invoice_id: ?string, amount: float|string}> */
    public array $allocationRows = [];

    public bool $showAllocationModal = false;

    public ?string $amend_reason = null;

    // --- Verify modal ------------------------------------------------------
    public bool $showVerifyModal = false;

    public ?string $verify_cheque_cleared_at = null;

    // --- Reverse modal -------------------------------------------------------
    public bool $showReverseModal = false;

    public ?string $reverse_reason = null;

    public function mount(Company $company, Payment $payment): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding happens before BelongsToCompany's scope is active
        // for this outside-Filament-panel request — same explicit guard
        // TallStackQuotationForm::mount() uses.
        abort_unless($payment->company_id === $company->id, 404);

        $this->payment = $payment->loadMissing(['client', 'receipt', 'allocations' => fn ($q) => $q->where('is_active', true)]);

        $this->resetAllocationRows();
    }

    private function resetAllocationRows(): void
    {
        $existing = $this->payment->allocations->map(fn ($allocation) => [
            'invoice_id' => (string) $allocation->invoice_id,
            'amount' => (float) $allocation->amount,
        ])->all();

        $this->allocationRows = $existing ?: [['invoice_id' => null, 'amount' => 0]];
    }

    public function openAllocationModal(): void
    {
        $this->resetAllocationRows();
        $this->amend_reason = null;
        $this->showAllocationModal = true;
    }

    public function addAllocationRow(): void
    {
        $this->allocationRows[] = ['invoice_id' => null, 'amount' => 0];
    }

    public function removeAllocationRow(int $index): void
    {
        unset($this->allocationRows[$index]);
        $this->allocationRows = array_values($this->allocationRows);

        if (empty($this->allocationRows)) {
            $this->allocationRows[] = ['invoice_id' => null, 'amount' => 0];
        }
    }

    public function saveAllocation(): void
    {
        $data = $this->validate([
            'allocationRows' => 'array|min:1',
            'allocationRows.*.invoice_id' => 'required|exists:invoices,id',
            'allocationRows.*.amount' => 'required|numeric|min:0.01',
        ]);

        $allocations = $this->mapAllocations($data['allocationRows']);

        try {
            if ($this->payment->receipt) {
                if (blank($this->amend_reason)) {
                    $this->addError('amend_reason', 'A reason is required to amend an already-receipted payment\'s allocation.');

                    return;
                }

                app(AmendPaymentAllocation::class)->amend($this->payment, $allocations, $this->amend_reason, auth()->user());
                $this->toast()->success('Allocation amended.')->send();
            } else {
                app(AllocateCustomerPayment::class)->allocate($this->payment, $allocations);
                $this->toast()->success('Payment allocated.')->send();
            }

            $this->payment->refresh()->load(['allocations' => fn ($q) => $q->where('is_active', true)]);
            $this->resetAllocationRows();
            $this->showAllocationModal = false;
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not save allocation', $e->getMessage())->send();
        }
    }

    /**
     * @param  array<int, array{invoice_id: string, amount: float|string}>  $rows
     * @return array<int|string, float>
     */
    private function mapAllocations(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            // Same fix as App\Filament\Resources\Payments\Tables\
            // PaymentsTable::mapAllocations() — a real bug found on PR #4:
            // assigning (not summing) here silently dropped every row but
            // the last for a repeated invoice_id, understating the total
            // while still reporting success.
            $result[$row['invoice_id']] = ($result[$row['invoice_id']] ?? 0.0) + (float) $row['amount'];
        }

        return $result;
    }

    // --- Verify ----------------------------------------------------------

    public function openVerifyModal(): void
    {
        $this->verify_cheque_cleared_at = null;
        $this->showVerifyModal = true;
    }

    public function verify(): void
    {
        try {
            app(VerifyCustomerPayment::class)->verify(
                $this->payment,
                auth()->user(),
                filled($this->verify_cheque_cleared_at) ? Carbon::parse($this->verify_cheque_cleared_at) : null,
            );

            $this->payment->refresh();
            $this->showVerifyModal = false;
            $this->toast()->success('Payment verified.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not verify payment', $e->getMessage())->send();
        }
    }

    // --- Issue receipt -----------------------------------------------------

    public function issueReceipt(): void
    {
        try {
            $receipt = app(IssuePaymentReceipt::class)->issue($this->payment);
            $this->payment->refresh()->load('receipt');
            $this->toast()->success('Receipt issued.', "Receipt #{$receipt->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not issue receipt', $e->getMessage())->send();
        }
    }

    // --- Reverse -----------------------------------------------------------

    public function openReverseModal(): void
    {
        $this->reverse_reason = null;
        $this->showReverseModal = true;
    }

    public function reverse(): void
    {
        $data = $this->validate([
            'reverse_reason' => 'required|string',
        ]);

        try {
            app(ReverseCustomerPayment::class)->reverse($this->payment, $data['reverse_reason'], auth()->user());

            $this->payment->refresh();
            $this->showReverseModal = false;
            $this->toast()->success('Payment reversed.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not reverse payment', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->payment->currency_code ?: $this->company->currency_code;

        $invoiceOptions = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->payment->client_id)
            ->orderByDesc('invoice_date')
            ->get(['id', 'number', 'total', 'balance'])
            ->map(fn (Invoice $invoice) => [
                'label' => $invoice->number.' — '.Money::format((float) $invoice->balance, $currency).' due',
                'value' => (string) $invoice->id,
            ])->all();

        $allocatedTotal = $this->payment->allocations->sum('amount');
        $remaining = (float) $this->payment->amount - (float) $allocatedTotal;

        return view('livewire.tallstack-payment-allocation', [
            'invoiceOptions' => $invoiceOptions,
            'currency' => $currency,
            'statusColor' => StatusColor::map($this->payment->status->getColor()),
            'incomingAmount' => Money::format((float) $this->payment->amount, $currency),
            'allocatedTotal' => Money::format((float) $allocatedTotal, $currency),
            'remaining' => Money::format($remaining, $currency),
            'remainingIsOverpaid' => $remaining < -0.0001,
            'isCheque' => $this->payment->method === PaymentMethod::Cheque->value,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'payments',
            'title' => 'Payment '.($this->payment->reference ?: '#'.$this->payment->id),
        ]);
    }
}
