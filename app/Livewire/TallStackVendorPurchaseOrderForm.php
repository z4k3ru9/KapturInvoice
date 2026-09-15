<?php

namespace App\Livewire;

use App\Actions\Procurement\ApproveVendorPoVariance;
use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Enums\CompanyRole;
use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Services\DocumentNumberGenerator;
use App\Services\Procurement\VendorPurchaseOrderTotalsCalculator;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack create/edit "line planning" page for a Vendor Purchase
 * Order — sibling to App\Livewire\TallStackVendorPurchaseOrders. Mirrors
 * App\Filament\Resources\VendorPurchaseOrders\Schemas\VendorPurchaseOrderForm
 * (header fields) and ...\RelationManagers\{ItemsRelationManager,
 * VariancesRelationManager} field-for-field: `status` is never a form
 * field, and the PO's `total` stays immutable once approved — every
 * mutation reuses the exact same App\Actions\Procurement\* classes the
 * Filament resource's row/relation-manager actions call.
 */
#[Layout('components.tallstack.app')]
class TallStackVendorPurchaseOrderForm extends Component
{
    use Interactions;

    public Company $company;

    public ?VendorPurchaseOrder $purchaseOrder = null;

    public ?string $vendor_id = null;

    public ?string $number = null;

    public ?string $po_date = null;

    public ?string $due_date = null;

    public ?string $delivery_date = null;

    public ?string $terms = null;

    public ?string $notes = null;

    // Line item modal state.
    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public ?string $item_product_id = null;

    public ?string $item_title = null;

    public ?string $item_description = null;

    public float $item_quantity = 1;

    public float $item_unit_cost = 0;

    // Variance modal state.
    public bool $showVarianceModal = false;

    public ?string $variance_reason = null;

    public float $variance_amount = 0;

    public function mount(Company $company, ?VendorPurchaseOrder $vendorPurchaseOrder = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        if ($vendorPurchaseOrder) {
            abort_unless($vendorPurchaseOrder->company_id === $company->id, 404);

            $this->purchaseOrder = $vendorPurchaseOrder->loadMissing(['items.product', 'vendor', 'variances.approvedBy']);
            $this->vendor_id = (string) $vendorPurchaseOrder->vendor_id;
            $this->number = $vendorPurchaseOrder->number;
            $this->po_date = $vendorPurchaseOrder->po_date?->toDateString();
            $this->due_date = $vendorPurchaseOrder->due_date?->toDateString();
            $this->delivery_date = $vendorPurchaseOrder->delivery_date?->toDateString();
            $this->terms = $vendorPurchaseOrder->terms;
            $this->notes = $vendorPurchaseOrder->notes;

            return;
        }

        $this->po_date = now()->toDateString();
    }

    public function save(): void
    {
        $data = $this->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'number' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'vendor_id' => $data['vendor_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'po_date' => $this->po_date,
            'due_date' => $this->due_date,
            'delivery_date' => $this->delivery_date,
            'terms' => $this->terms,
            'notes' => $this->notes,
        ];

        if ($this->purchaseOrder) {
            $this->purchaseOrder->update($payload);
            $this->toast()->success('Vendor purchase order saved.')->send();

            return;
        }

        if (blank($payload['number'])) {
            $payload['number'] = app(DocumentNumberGenerator::class)->next($this->company, 'vendor_purchase_order');
        }

        $payload['company_id'] = $this->company->id;
        $payload['status'] = VendorPurchaseOrderStatus::Draft;

        $po = VendorPurchaseOrder::create($payload);

        $this->toast()->success('Vendor purchase order created.', 'Add line items below, then approve when ready.')->send();

        $this->redirect(route('tallstack.vendor-purchase-orders.edit', [$this->company, $po]), navigate: false);
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
        $this->item_title = $item->title;
        $this->item_description = $item->description;
        $this->item_quantity = (float) $item->quantity;
        $this->item_unit_cost = (float) $item->unit_cost;
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

    public function saveItem(): void
    {
        if (! $this->purchaseOrder || $this->purchaseOrder->status !== VendorPurchaseOrderStatus::Draft) {
            return;
        }

        $data = $this->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_description' => ['nullable', 'string'],
            'item_quantity' => ['required', 'numeric', 'min:0.0001'],
            'item_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $productId = $this->item_product_id
            ? Product::query()->where('company_id', $this->company->id)->whereKey($this->item_product_id)->value('id')
            : null;

        $payload = [
            'product_id' => $productId,
            'title' => $data['item_title'],
            'description' => $data['item_description'],
            'quantity' => $data['item_quantity'],
            'unit_cost' => $data['item_unit_cost'],
        ];

        if ($this->editingItemId) {
            $item = $this->scopedItem($this->editingItemId);

            if ($item) {
                $item->update($payload);
            }
        } else {
            $payload['vendor_purchase_order_id'] = $this->purchaseOrder->id;
            $payload['sort_order'] = ((int) $this->purchaseOrder->items()->max('sort_order')) + 1;
            VendorPurchaseOrderItem::create($payload);
        }

        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($this->purchaseOrder);
        $this->purchaseOrder->refresh()->load('items.product');

        $this->showItemModal = false;
        $this->resetItemForm();
        $this->toast()->success('Line item saved.')->send();
    }

    public function deleteItem(int $id): void
    {
        if (! $this->purchaseOrder || $this->purchaseOrder->status !== VendorPurchaseOrderStatus::Draft) {
            return;
        }

        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $item->delete();
        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($this->purchaseOrder);
        $this->purchaseOrder->refresh()->load('items.product');

        $this->toast()->success('Line item removed.')->send();
    }

    private function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->item_product_id = null;
        $this->item_title = null;
        $this->item_description = null;
        $this->item_quantity = 1;
        $this->item_unit_cost = 0;
    }

    private function scopedItem(int $id): ?VendorPurchaseOrderItem
    {
        if (! $this->purchaseOrder) {
            return null;
        }

        return $this->purchaseOrder->items->firstWhere('id', $id);
    }

    /**
     * Same dynamic-row reorder contract as TallStackInvoiceForm::reorderItems()
     * — one Livewire round trip reassigns `sort_order` sequentially, and
     * it's a no-op once the PO has left Draft (same guard saveItem()/
     * deleteItem() already apply above).
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderItems(array $orderedIds): void
    {
        if (! $this->purchaseOrder || $this->purchaseOrder->status !== VendorPurchaseOrderStatus::Draft) {
            return;
        }

        $orderedIds = array_map('intval', $orderedIds);

        $owned = $this->purchaseOrder->items()->whereIn('id', $orderedIds)->pluck('id')->all();

        if (count($owned) !== count($orderedIds) || array_diff($orderedIds, $owned) !== []) {
            return;
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                VendorPurchaseOrderItem::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        $this->purchaseOrder->refresh()->load('items.product');
    }

    // --- Status transition ----------------------------------------------

    public function approve(): void
    {
        if (! $this->purchaseOrder) {
            return;
        }

        try {
            app(ApproveVendorPurchaseOrder::class)->approve($this->purchaseOrder);
            $this->purchaseOrder->refresh();
            $this->toast()->success('Vendor purchase order approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve vendor purchase order', $e->getMessage())->send();
        }
    }

    // --- Vendor PO variance (Owner/Admin only) ---------------------------

    /** Gates the "Record variance" button itself — the action re-checks this role server-side regardless. */
    public function getCanApproveVarianceProperty(): bool
    {
        return auth()->user()->hasCompanyRole($this->company, ...CompanyRole::vendorPoVarianceApprovalRoles());
    }

    public function openVarianceModal(): void
    {
        $this->variance_reason = null;
        $this->variance_amount = 0;
        $this->showVarianceModal = true;
    }

    public function recordVariance(): void
    {
        if (! $this->purchaseOrder) {
            return;
        }

        $data = $this->validate([
            'variance_reason' => ['required', 'string'],
            'variance_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            app(ApproveVendorPoVariance::class)->approve(
                $this->purchaseOrder,
                auth()->user(),
                $data['variance_reason'],
                (float) $data['variance_amount'],
            );

            $this->purchaseOrder->refresh()->load('variances.approvedBy');
            $this->showVarianceModal = false;
            $this->toast()->success('Variance approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve variance', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $items = $this->purchaseOrder
            ? $this->purchaseOrder->items->sortBy('sort_order')->map(fn (VendorPurchaseOrderItem $item) => [
                'id' => $item->id,
                'image' => $item->product?->getImageDataUri(),
                'title' => $item->title,
                'quantity' => (float) $item->quantity,
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                'line_total' => Money::format((float) $item->line_total, $currency),
            ])->values()
            : collect();

        $variances = $this->purchaseOrder
            ? $this->purchaseOrder->variances->map(fn ($v) => [
                'reason' => $v->reason,
                'amount' => Money::format((float) $v->amount, $currency),
                'approved_by' => $v->approvedBy?->name ?? '—',
                'approved_at' => $v->approved_at?->format('d M Y H:i') ?? '—',
            ])
            : collect();

        return view('livewire.tallstack-vendor-purchase-order-form', [
            'vendors' => Vendor::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'items' => $items,
            'variances' => $variances,
            'currency' => $currency,
            'statusColor' => $this->purchaseOrder ? StatusColor::map($this->purchaseOrder->status->getColor()) : null,
            'total' => $this->purchaseOrder ? Money::format((float) $this->purchaseOrder->total, $currency) : Money::format(0, $currency),
            'paymentCeiling' => $this->purchaseOrder ? Money::format($this->purchaseOrder->paymentCeiling(), $currency) : null,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'vendor-purchase-orders',
            'title' => $this->purchaseOrder ? "Vendor PO {$this->purchaseOrder->number}" : 'New vendor purchase order',
        ]);
    }
}
