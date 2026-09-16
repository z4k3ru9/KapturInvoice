{{--
    Inline add/edit line-item form for TallStackQuotationForm (Phase 13
    repair plan item 1 — replaces the previous <x-modal wire="showItemModal">
    round-trip). Same shape as partials/invoice-item-form.blade.php minus
    the Taxes field (Quotation items carry no tax pivot). Shares the parent
    view's `$products` scope via Blade's default @include variable sharing.
--}}
<div class="flex flex-col gap-3">
    <div class="grid sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <x-select.styled wire:model.live="item_product_id" label="Product (optional)" searchable
                :options="$products->map(fn ($p) => ['label' => $p->name, 'value' => (string) $p->id])->all()" />
        </div>
        <div class="sm:col-span-2">
            <x-input wire:model="item_title" label="Title" required />
        </div>
        <div class="sm:col-span-4">
            <x-textarea wire:model="item_description" label="Description" rows="2" />
        </div>
        <x-input wire:model="item_quantity" label="Quantity" type="number" step="1" min="1" />
        <x-select.styled wire:model="item_unit" label="Unit" clearable
            :options="collect(\App\Enums\UnitOfMeasure::cases())->map(fn ($u) => ['label' => $u->getLabel(), 'value' => $u->value])->all()" />
        <x-currency wire:model="item_unit_cost" label="Unit cost" locale="id-ID" :decimals="2" :precision="4" decimal />
        @if ($item_discount_is_percentage)
            <x-input wire:model="item_discount" label="Discount" type="number" step="0.01" suffix="%" />
        @else
            <x-currency wire:model="item_discount" label="Discount" locale="id-ID" :decimals="2" :precision="4" decimal />
        @endif
        <div class="flex items-end pb-2 sm:col-span-2">
            <button type="button" wire:click="$toggle('item_discount_is_percentage')"
                class="inline-flex items-center gap-1 text-xs font-medium transition-colors {{ $item_discount_is_percentage ? 'text-[color:var(--ts-primary)]' : 'text-gray-400 dark:text-gray-500! hover:text-gray-600 dark:hover:text-gray-300!' }}">
                @if ($item_discount_is_percentage)
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                @endif
                Discount is a percentage
            </button>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <x-button text="Cancel" color="gray" sm wire:click="cancelItemForm" />
        <x-button text="{{ $editingItemId ? 'Save' : 'Add' }}" color="blue" sm wire:click="saveItem" loading="saveItem" spinner="dots" />
    </div>
</div>
