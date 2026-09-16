{{--
    Inline add/edit line-item form for TallStackInvoiceForm (Phase 13 repair
    plan item 1 — replaces the previous <x-modal wire="showItemModal">
    round-trip). Rendered directly inside the Line Items table, either as
    the row being edited (`editingItemId` set) or as a trailing "add row"
    (no `editingItemId`) — see tallstack-invoice-form.blade.php's Line Items
    tab. Shares the parent view's `$products`/`$taxRates` scope via Blade's
    default @include variable sharing; no props needed.
--}}
<div class="flex flex-col gap-3">
    <div class="grid sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <x-select.styled wire:model.live="item_product_id" label="Product (optional)" searchable clearable
                :options="$products->map(fn ($p) => ['label' => $p->name, 'value' => (string) $p->id])->all()" />
        </div>
        <div class="sm:col-span-2">
            <x-input wire:model="item_title" label="Title" required />
        </div>
        <div class="sm:col-span-4">
            <x-textarea wire:model="item_description" label="Description" rows="2" />
        </div>
        <x-input wire:model="item_quantity" label="Quantity" type="number" step="0.0001" />
        <x-currency wire:model="item_unit_cost" label="Unit cost" locale="id-ID" :decimals="2" :precision="4" decimal />
        <x-input wire:model="item_discount" label="Discount" type="number" step="0.01" />
        <div class="flex items-end pb-2 sm:col-span-2">
            <x-toggle wire:model="item_discount_is_percentage" label="Discount is a percentage" />
        </div>
        <div class="sm:col-span-4">
            <x-select.styled wire:model="item_tax_rate_ids" label="Taxes" :multiple="true" searchable
                :options="$taxRates->map(fn ($t) => ['label' => $t->name, 'value' => (string) $t->id])->all()" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <x-button text="Cancel" color="gray" sm wire:click="cancelItemForm" />
        {{-- color="brand" — this row's own single commit action (Primary role). --}}
        <x-button text="{{ $editingItemId ? 'Save' : 'Add' }}" color="brand" sm wire:click="saveItem" loading="saveItem" spinner="dots" />
    </div>
</div>
