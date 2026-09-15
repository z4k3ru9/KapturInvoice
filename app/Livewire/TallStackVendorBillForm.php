<?php

namespace App\Livewire;

use App\Actions\Procurement\AllocateJobCost;
use App\Actions\Procurement\AmendVendorPayment;
use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\IssueVendorPaymentReceipt;
use App\Actions\Procurement\RecordVendorPayment;
use App\Actions\Procurement\ReverseVendorPayment;
use App\Actions\Procurement\SubmitVendorBill;
use App\Actions\Procurement\VerifyVendorPayment;
use App\Enums\CompanyRole;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPayment;
use App\Models\VendorPurchaseOrder;
use App\Services\DocumentNumberGenerator;
use App\Services\Procurement\VendorBillTotalsCalculator;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Vendor Bill Detail & Shared Allocation" page — sibling to
 * App\Livewire\TallStackVendorBills. Mirrors
 * App\Filament\Resources\VendorBills\Schemas\VendorBillForm (header
 * fields) and ...\RelationManagers\{ItemsRelationManager,
 * PaymentsRelationManager} field-for-field: `status` is never a form
 * field, and every mutation (submit/approve/record/verify/issue receipt/
 * amend/reverse payment, allocate cost to a job) reuses the exact same
 * App\Actions\Procurement\* class the Filament resource's actions call —
 * a presentation-layer swap only. Net/tax/gross line components stay
 * separate throughout, per docs/rebuild/specs/FINALIZED-DECISIONS.md §3.
 */
#[Layout('components.tallstack.app')]
class TallStackVendorBillForm extends Component
{
    use Interactions, WithFileUploads;

    public Company $company;

    public ?VendorBill $bill = null;

    public ?string $vendor_id = null;

    public ?string $vendor_purchase_order_id = null;

    public ?string $number = null;

    public ?string $bill_date = null;

    public ?string $due_date = null;

    public ?string $notes = null;

    // Line item modal state.
    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public ?string $item_product_id = null;

    public ?string $item_vendor_po_item_id = null;

    public ?string $item_title = null;

    public ?string $item_description = null;

    public float $item_quantity = 1;

    public float $item_unit_cost = 0;

    public float $item_net_amount = 0;

    public float $item_tax_amount = 0;

    // Job-cost allocation modal state.
    public bool $showAllocateModal = false;

    public ?int $allocatingItemId = null;

    public ?string $allocate_sales_order_id = null;

    public float $allocate_amount = 0;

    public ?float $allocate_quantity = null;

    // Record-payment modal state.
    public bool $showPaymentModal = false;

    public float $payment_amount = 0;

    public ?string $payment_date = null;

    public ?string $payment_method = null;

    public ?string $payment_reference = null;

    public $payment_proof = null;

    public ?string $payment_notes = null;

    // Verify-payment modal state.
    public bool $showVerifyModal = false;

    public ?int $verifyingPaymentId = null;

    public ?string $verify_cheque_cleared_at = null;

    // Amend-payment modal state.
    public bool $showAmendModal = false;

    public ?int $amendingPaymentId = null;

    public float $amend_amount = 0;

    public ?string $amend_reason = null;

    // Reverse-payment modal state.
    public bool $showReverseModal = false;

    public ?int $reversingPaymentId = null;

    public ?string $reverse_reason = null;

    public function mount(Company $company, ?VendorBill $vendorBill = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        if ($vendorBill) {
            abort_unless($vendorBill->company_id === $company->id, 404);

            $this->bill = $vendorBill->loadMissing(['items.product', 'items.jobCostAllocations.salesOrder', 'vendor', 'vendorPurchaseOrder', 'payments.receipt']);
            $this->vendor_id = (string) $vendorBill->vendor_id;
            $this->vendor_purchase_order_id = (string) $vendorBill->vendor_purchase_order_id;
            $this->number = $vendorBill->number;
            $this->bill_date = $vendorBill->bill_date?->toDateString();
            $this->due_date = $vendorBill->due_date?->toDateString();
            $this->notes = $vendorBill->notes;

            return;
        }

        $this->bill_date = now()->toDateString();
    }

    public function save(): void
    {
        $data = $this->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'vendor_purchase_order_id' => ['required', 'exists:vendor_purchase_orders,id'],
            'number' => ['nullable', 'string', 'max:255'],
            'bill_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'vendor_id' => $data['vendor_id'],
            'vendor_purchase_order_id' => $data['vendor_purchase_order_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'bill_date' => $this->bill_date,
            'due_date' => $this->due_date,
            'notes' => $this->notes,
        ];

        if ($this->bill) {
            $this->bill->update($payload);
            $this->toast()->success('Vendor bill saved.')->send();

            return;
        }

        if (blank($payload['number'])) {
            $payload['number'] = app(DocumentNumberGenerator::class)->next($this->company, 'vendor_bill');
        }

        $payload['company_id'] = $this->company->id;
        $payload['status'] = VendorBillStatus::Draft;

        $bill = VendorBill::create($payload);

        $this->toast()->success('Vendor bill created.', 'Add line items below, then continue the workflow.')->send();

        $this->redirect(route('tallstack.vendor-bills.edit', [$this->company, $bill]), navigate: false);
    }

    /** Vendor POs offered are scoped to the selected vendor's own Approved POs — mirrors VendorBillForm's bounded options() closure. */
    public function getVendorPurchaseOrderOptionsProperty()
    {
        if (! $this->vendor_id) {
            return collect();
        }

        return VendorPurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->where('vendor_id', $this->vendor_id)
            ->where('status', VendorPurchaseOrderStatus::Approved)
            ->get(['id', 'number']);
    }

    // --- Line items -----------------------------------------------------

    public function addItem(): void
    {
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function editItem(int $id): void
    {
        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $this->editingItemId = $item->id;
        $this->item_product_id = $item->product_id ? (string) $item->product_id : null;
        $this->item_vendor_po_item_id = $item->vendor_purchase_order_item_id ? (string) $item->vendor_purchase_order_item_id : null;
        $this->item_title = $item->title;
        $this->item_description = $item->description;
        $this->item_quantity = (float) $item->quantity;
        $this->item_unit_cost = (float) $item->unit_cost;
        $this->item_net_amount = (float) $item->net_amount;
        $this->item_tax_amount = (float) $item->tax_amount;
        $this->showItemModal = true;
    }

    public function updatedItemProductId(?string $value): void
    {
        if (! $value) {
            return;
        }

        if ($product = Product::query()->where('company_id', $this->company->id)->find($value)) {
            $this->item_title = $product->name;
            $this->item_unit_cost = (float) $product->unit_cost;
        }
    }

    /** Vendor PO line options bounded to this bill's own PO's items — never an unbounded pluck. */
    public function getVendorPoItemOptionsProperty()
    {
        return $this->bill?->vendorPurchaseOrder?->items ?? collect();
    }

    public function saveItem(): void
    {
        if (! $this->bill || $this->bill->status !== VendorBillStatus::Draft) {
            return;
        }

        $data = $this->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_description' => ['nullable', 'string'],
            'item_quantity' => ['required', 'numeric', 'min:0.0001'],
            'item_unit_cost' => ['required', 'numeric', 'min:0'],
            'item_net_amount' => ['required', 'numeric', 'min:0'],
            'item_tax_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $productId = $this->item_product_id
            ? Product::query()->where('company_id', $this->company->id)->whereKey($this->item_product_id)->value('id')
            : null;

        $payload = [
            'product_id' => $productId,
            'vendor_purchase_order_item_id' => $this->item_vendor_po_item_id ?: null,
            'title' => $data['item_title'],
            'description' => $data['item_description'],
            'quantity' => $data['item_quantity'],
            'unit_cost' => $data['item_unit_cost'],
            'net_amount' => $data['item_net_amount'],
            'tax_amount' => $data['item_tax_amount'],
        ];

        if ($this->editingItemId) {
            $item = $this->scopedItem($this->editingItemId);

            if ($item) {
                $item->update($payload);
            }
        } else {
            $payload['vendor_bill_id'] = $this->bill->id;
            $payload['sort_order'] = ((int) $this->bill->items()->max('sort_order')) + 1;
            VendorBillItem::create($payload);
        }

        app(VendorBillTotalsCalculator::class)->recalculate($this->bill);
        $this->refreshBill();

        $this->showItemModal = false;
        $this->resetItemForm();
        $this->toast()->success('Line item saved.')->send();
    }

    public function deleteItem(int $id): void
    {
        if (! $this->bill || $this->bill->status !== VendorBillStatus::Draft) {
            return;
        }

        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $item->delete();
        app(VendorBillTotalsCalculator::class)->recalculate($this->bill);
        $this->refreshBill();

        $this->toast()->success('Line item removed.')->send();
    }

    private function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->item_product_id = null;
        $this->item_vendor_po_item_id = null;
        $this->item_title = null;
        $this->item_description = null;
        $this->item_quantity = 1;
        $this->item_unit_cost = 0;
        $this->item_net_amount = 0;
        $this->item_tax_amount = 0;
    }

    private function scopedItem(int $id): ?VendorBillItem
    {
        if (! $this->bill) {
            return null;
        }

        return $this->bill->items->firstWhere('id', $id);
    }

    /**
     * Same dynamic-row reorder contract as TallStackInvoiceForm::reorderItems()
     * — one Livewire round trip reassigns `sort_order` sequentially, and
     * it's a no-op once the bill has left Draft (same guard saveItem()/
     * deleteItem() already apply above).
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderItems(array $orderedIds): void
    {
        if (! $this->bill || $this->bill->status !== VendorBillStatus::Draft) {
            return;
        }

        $orderedIds = array_map('intval', $orderedIds);

        $owned = $this->bill->items()->whereIn('id', $orderedIds)->pluck('id')->all();

        if (count($owned) !== count($orderedIds) || array_diff($orderedIds, $owned) !== []) {
            return;
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                VendorBillItem::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        $this->refreshBill();
    }

    // --- Job-cost allocation ("shared allocation") ------------------------

    public function openAllocateModal(int $itemId): void
    {
        $this->allocatingItemId = $itemId;
        $this->allocate_sales_order_id = null;
        $this->allocate_amount = 0;
        $this->allocate_quantity = null;
        $this->showAllocateModal = true;
    }

    public function allocate(): void
    {
        $item = $this->scopedItem($this->allocatingItemId ?? 0);

        if (! $item) {
            return;
        }

        $data = $this->validate([
            'allocate_sales_order_id' => ['required', 'exists:sales_orders,id'],
            'allocate_amount' => ['required', 'numeric', 'min:0.01'],
            'allocate_quantity' => ['nullable', 'numeric'],
        ]);

        try {
            $salesOrder = SalesOrder::query()->where('company_id', $this->company->id)->findOrFail($data['allocate_sales_order_id']);

            app(AllocateJobCost::class)->allocate(
                $item,
                $salesOrder,
                (float) $data['allocate_amount'],
                filled($data['allocate_quantity'] ?? null) ? (float) $data['allocate_quantity'] : null,
            );

            $this->refreshBill();
            $this->showAllocateModal = false;
            $this->toast()->success('Cost allocated to job.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not allocate cost', $e->getMessage())->send();
        }
    }

    /** Bounded/server-searched job picker, never an unbounded pluck — mirrors ItemsRelationManager's own getSearchResultsUsing(). */
    public function searchSalesOrders(string $search = '')
    {
        return SalesOrder::query()
            ->where('company_id', $this->company->id)
            ->when($search, fn ($q) => $q->where('number', 'like', "%{$search}%"))
            ->limit(50)
            ->get(['id', 'number']);
    }

    // --- Bill status transitions ------------------------------------------

    public function submit(): void
    {
        if (! $this->bill) {
            return;
        }

        try {
            app(SubmitVendorBill::class)->submit($this->bill);
            $this->refreshBill();
            $this->toast()->success('Vendor bill submitted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not submit vendor bill', $e->getMessage())->send();
        }
    }

    public function approve(): void
    {
        if (! $this->bill) {
            return;
        }

        try {
            app(ApproveVendorBill::class)->approve($this->bill, auth()->user());
            $this->refreshBill();
            $this->toast()->success('Vendor bill approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve vendor bill', $e->getMessage())->send();
        }
    }

    // --- Vendor payments --------------------------------------------------

    public function openPaymentModal(): void
    {
        $this->payment_amount = (float) ($this->bill->balance ?? 0);
        $this->payment_date = now()->toDateString();
        $this->payment_method = null;
        $this->payment_reference = null;
        $this->payment_proof = null;
        $this->payment_notes = null;
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        if (! $this->bill) {
            return;
        }

        $data = $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string'],
            'payment_reference' => ['nullable', 'string'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'payment_notes' => ['nullable', 'string'],
        ]);

        $proofPath = null;

        if ($this->payment_proof instanceof UploadedFile) {
            $proofPath = $this->payment_proof->store('vendor-payment-proofs');
        }

        try {
            app(RecordVendorPayment::class)->record($this->bill, [
                'amount' => $data['payment_amount'],
                'payment_date' => $data['payment_date'] ?? now(),
                'method' => $data['payment_method'] ?? null,
                'reference' => $data['payment_reference'] ?? null,
                'proof_path' => $proofPath,
                'notes' => $data['payment_notes'] ?? null,
            ]);

            $this->refreshBill();
            $this->showPaymentModal = false;
            $this->toast()->success('Vendor payment recorded.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not record vendor payment', $e->getMessage())->send();
        }
    }

    public function openVerifyModal(int $paymentId): void
    {
        $this->verifyingPaymentId = $paymentId;
        $this->verify_cheque_cleared_at = null;
        $this->showVerifyModal = true;
    }

    public function verifyPayment(): void
    {
        $payment = $this->scopedPayment($this->verifyingPaymentId ?? 0);

        if (! $payment) {
            return;
        }

        try {
            app(VerifyVendorPayment::class)->verify(
                $payment,
                auth()->user(),
                filled($this->verify_cheque_cleared_at) ? Carbon::parse($this->verify_cheque_cleared_at) : null,
            );

            $this->refreshBill();
            $this->showVerifyModal = false;
            $this->toast()->success('Vendor payment verified.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not verify vendor payment', $e->getMessage())->send();
        }
    }

    public function issueReceipt(int $paymentId): void
    {
        $payment = $this->scopedPayment($paymentId);

        if (! $payment) {
            return;
        }

        try {
            app(IssueVendorPaymentReceipt::class)->issue($payment);
            $this->refreshBill();
            $this->toast()->success('Vendor payment receipt issued.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not issue receipt', $e->getMessage())->send();
        }
    }

    public function openAmendModal(int $paymentId): void
    {
        $payment = $this->scopedPayment($paymentId);

        if (! $payment) {
            return;
        }

        $this->amendingPaymentId = $payment->id;
        $this->amend_amount = (float) $payment->amount;
        $this->amend_reason = null;
        $this->showAmendModal = true;
    }

    public function amendPayment(): void
    {
        $payment = $this->scopedPayment($this->amendingPaymentId ?? 0);

        if (! $payment) {
            return;
        }

        $data = $this->validate([
            'amend_amount' => ['required', 'numeric', 'min:0.01'],
            'amend_reason' => ['required', 'string'],
        ]);

        try {
            app(AmendVendorPayment::class)->amend($payment, (float) $data['amend_amount'], $data['amend_reason'], auth()->user());
            $this->refreshBill();
            $this->showAmendModal = false;
            $this->toast()->success('Vendor payment amended.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not amend vendor payment', $e->getMessage())->send();
        }
    }

    public function openReverseModal(int $paymentId): void
    {
        $this->reversingPaymentId = $paymentId;
        $this->reverse_reason = null;
        $this->showReverseModal = true;
    }

    public function reversePayment(): void
    {
        $payment = $this->scopedPayment($this->reversingPaymentId ?? 0);

        if (! $payment) {
            return;
        }

        $data = $this->validate([
            'reverse_reason' => ['required', 'string'],
        ]);

        try {
            app(ReverseVendorPayment::class)->reverse($payment, $data['reverse_reason'], auth()->user());
            $this->refreshBill();
            $this->showReverseModal = false;
            $this->toast()->success('Vendor payment reversed.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not reverse vendor payment', $e->getMessage())->send();
        }
    }

    private function scopedPayment(int $id): ?VendorPayment
    {
        if (! $this->bill) {
            return null;
        }

        return $this->bill->payments->firstWhere('id', $id);
    }

    private function refreshBill(): void
    {
        $this->bill?->refresh()->load(['items.product', 'items.jobCostAllocations.salesOrder', 'vendor', 'vendorPurchaseOrder', 'payments.receipt']);
    }

    /** Gates verifying/amending/reversing a vendor payment in this UI — the actions re-check this role server-side regardless. */
    public function getCanVerifyPaymentsProperty(): bool
    {
        return auth()->user()->hasCompanyRole($this->company, ...CompanyRole::paymentVerificationRoles());
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $items = $this->bill
            ? $this->bill->items->sortBy('sort_order')->map(fn (VendorBillItem $item) => [
                'id' => $item->id,
                'image' => $item->product?->getImageDataUri(),
                'title' => $item->title,
                'quantity' => (float) $item->quantity,
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                'net_amount' => Money::format((float) $item->net_amount, $currency),
                'tax_amount' => Money::format((float) $item->tax_amount, $currency),
                'line_total' => Money::format((float) $item->line_total, $currency),
                'unallocated' => Money::format($item->unallocatedAmount(), $currency),
                'unallocated_raw' => $item->unallocatedAmount(),
                'allocations' => $item->jobCostAllocations->map(fn ($a) => [
                    'job' => $a->salesOrder?->number ?? '—',
                    'amount' => Money::format((float) $a->amount, $currency),
                ]),
            ])->values()
            : collect();

        $payments = $this->bill
            ? $this->bill->payments->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->receipt?->number ?? '—',
                'amount' => Money::format((float) $p->amount, $currency),
                'payment_date' => $p->payment_date?->format('d M Y') ?? '—',
                'method' => $p->method ?? '—',
                'reference' => $p->reference ?? '—',
                'status' => $p->status,
                'status_label' => $p->status->getLabel(),
                'status_color' => StatusColor::map($p->status->getColor()),
                'has_receipt' => $p->receipt !== null,
                'receipt_url' => $p->receipt ? route('vendor-payment-receipts.pdf', $p->receipt) : null,
            ])
            : collect();

        return view('livewire.tallstack-vendor-bill-form', [
            'vendors' => Vendor::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'items' => $items,
            'payments' => $payments,
            'currency' => $currency,
            'statusColor' => $this->bill ? StatusColor::map($this->bill->status->getColor()) : null,
            'total' => $this->bill ? Money::format((float) $this->bill->total, $currency) : Money::format(0, $currency),
            'amountPaid' => $this->bill ? Money::format((float) $this->bill->amount_paid, $currency) : Money::format(0, $currency),
            'balance' => $this->bill ? Money::format((float) $this->bill->balance, $currency) : Money::format(0, $currency),
            'paymentCeiling' => $this->bill?->vendorPurchaseOrder ? Money::format($this->bill->vendorPurchaseOrder->paymentCeiling(), $currency) : null,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'vendor-bills',
            'title' => $this->bill ? "Vendor bill {$this->bill->number}" : 'New vendor bill',
        ]);
    }
}
