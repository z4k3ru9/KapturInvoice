<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Products', 'icon' => 'cube']]" title="Products">
        <x-slot:actions>
            {{-- color="blue", not "primary" — same reasoning as every other
                 general-function button on these TALL-stack pages (see
                 app.blade.php's own "+New" button). --}}
            <x-button text="New product" icon="plus" color="blue" sm class="h-9" wire:click="openCreateModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Type filter pills — every real CatalogItemType case, not
                     an invented merged grouping. --}}
                <x-tallstack.filter-dropdown
                    label="{{ $typeFilter === null ? 'All types' : collect($types)->first(fn ($case) => $case->value === $typeFilter)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All types']])->concat(collect($types)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$typeFilter"
                    method="filterType"
                />

                <div class="flex items-center gap-2 w-full sm:flex-1 sm:min-w-[240px]">
                    <div class="flex-1 min-w-0">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search name or SKU…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'image', 'label' => '', 'sortable' => false],
            ['index' => 'sku', 'label' => 'SKU'],
            ['index' => 'name', 'label' => 'Name'],
            ['index' => 'type', 'label' => 'Type'],
            ['index' => 'unit', 'label' => 'Unit'],
            ['index' => 'price', 'label' => 'Price', 'align' => 'right'],
            ['index' => 'tax_category_label', 'label' => 'Tax category'],
            ['index' => 'stock_flag', 'label' => 'Normally stocked'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$products" paginate loading>
            {{--
                Square thumbnail (DESIGN.md §15: "a small square thumbnail
                (~32-40px)... never its own labeled column header" — hence
                the blank label above), contrasted with the Users resource's
                circular avatars elsewhere in this app — object-cover, not
                rounded-full. Absence is the expected common case: no
                broken-image icon or gray placeholder box, just an empty cell.
            --}}
            {{--
                Wrapped in a fixed-size div rather than sizing the <img>
                directly: TallStackUI's own compiled CSS gives every <img>
                a global `max-width:100%` (a percentage), which inside this
                column's unset-width <td> makes the browser's table
                auto-layout algorithm compute the column at the image's
                near-zero intrinsic width regardless of the `w-9`/`h-9`
                utility classes on the <img> itself — confirmed via a real
                bounding-box measurement (the image rendered ~8px wide
                instead of 36px). A block-level wrapper div with a
                non-percentage fixed width sizes the column correctly; the
                <img> then just fills it with object-cover.
            --}}
            @interact('column_image', $row)
                @if ($row['image'])
                    <div class="w-9 h-9 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700!">
                        <img src="{{ $row['image'] }}" alt="" class="w-full h-full object-cover">
                    </div>
                @endif
            @endinteract

            @interact('column_type', $row)
                <x-badge text="{{ $row['type_label'] }}" :color="$row['type_color']" sm />
            @endinteract

            @interact('column_tax_category_label', $row)
                <x-badge text="{{ $row['tax_category_label'] }}" :color="$row['tax_category_color']" sm />
            @endinteract

            {{-- Display-only — never wired to real inventory/availability
                 (App\Models\Product's own docblock: "stock_flag is a label
                 only... never drive an in-stock/availability computation"). --}}
            @interact('column_stock_flag', $row)
                @if ($row['stock_flag'])
                    <x-badge text="Stocked" color="green" sm />
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_actions', $row)
                @php
                    $extraActions = [];
                    if ($row['has_image']) {
                        $extraActions[] = ['text' => 'Create proposal snippet', 'icon' => 'photo', 'click' => 'createProposalSnippet('.$row['id'].')'];
                    }
                    $extraActions[] = ['text' => 'Delete', 'icon' => 'trash', 'click' => 'delete('.$row['id'].')', 'confirm' => 'Delete this product?'];
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="openEditModal({{ $row['id'] }})" tooltip="Edit" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No products found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Create/Edit modal — Products is one of CLAUDE.md's 14
         modal-based-Create/Edit resources, confirmed (not overridden) by
         the fetched Stitch mockup rendering the same edit form as a modal
         dialog over the register table. Field-for-field ProductForm's own
         set — nothing invented, nothing dropped. --}}
    <x-modal wire="showFormModal" title="{{ $editingId ? 'Edit product' : 'New product' }}" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="name" label="Name" required />

            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="sku" label="SKU" />
                <x-select.styled wire:model="type" label="Type" required
                    :options="collect($types)->map(fn ($t) => ['label' => $t->getLabel(), 'value' => $t->value])->all()" />
            </div>

            {{-- Picture — plain upload, no crop tooling, matching
                 ProductForm's own "square rendering is done by the
                 thumbnail column's object-cover, not by cropping the
                 source file" (DESIGN §15). --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200! mb-1.5">Picture</label>
                @if ($existingImageDataUri && ! $photo)
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700!">
                            <img src="{{ $existingImageDataUri }}" alt="" class="w-full h-full object-cover">
                        </div>
                        <x-button text="Remove" icon="trash" color="red" sm wire:click="removePhoto" />
                    </div>
                @endif
                <x-upload wire:model="photo" accept="image/jpeg,image/png,image/webp" tip="JPG, PNG, WEBP · max 2 MB. Renders as an unlabeled square thumbnail on lists/quotations." :preview="true" />
                <p class="text-[11px] text-gray-400 mt-1">Optional. Shown as a thumbnail on quotation line items and reusable in proposal snippets.</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-select.styled wire:model="unit" label="Unit" clearable
                    hint="Not required for a service/labor line."
                    :options="collect(\App\Enums\UnitOfMeasure::cases())->map(fn ($u) => ['label' => $u->getLabel(), 'value' => $u->value])->all()" />
                <x-currency wire:model="unit_cost" label="Default price" locale="id-ID" :decimals="2" :precision="4" decimal symbol="{{ $currency }}" required />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-select.styled wire:model="tax_category" label="Tax category" required
                    :options="collect($taxCategories)->map(fn ($t) => ['label' => $t->getLabel(), 'value' => $t->value])->all()" />
                <x-select.styled wire:model="default_tax_rate_id" label="Default tax rate" searchable clearable
                    :options="$taxRates->map(fn ($t) => ['label' => $t->name, 'value' => (string) $t->id])->all()" />
            </div>

            {{-- Shares one taxonomy with Price List Items (repair plan
                 Phase 10b / decision gate G3) — same
                 <x-tallstack.category-select> searchable/inline-create
                 picker Phase 10a built, sourced from every distinct
                 category already used across this tenant's Products AND
                 Price List Items (see TallStackProducts::render()'s
                 $categories). A Product linked via price_list_item_id
                 gets this pre-filled once at link time
                 (App\Services\ProductSync); freely editable afterward. --}}
            <x-tallstack.category-select wire:model="category" :options="$categories" />

            <div>
                <x-toggle wire:model="stock_flag" label="Normally stocked" />
                <p class="text-[11px] text-gray-400 mt-1">A label only — this company does not track real inventory/availability.</p>
            </div>

            <x-textarea wire:model="description" label="Description" hint="Printed on quotations." rows="3" />

            {{-- Read-only linkage display, never an editable field here —
                 set only via the Price List Items resource's "Create/update
                 product" action (App\Services\ProductSync). --}}
            @if ($priceListItemLabel)
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60! border border-gray-200 dark:border-gray-700! px-3 py-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400!">Linked price list item</p>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100!">{{ $priceListItemLabel }}</p>
                </div>
            @endif

            @if ($legacyProductId)
                <p class="text-[11px] text-gray-400">Legacy InvoiceNinja product id: {{ $legacyProductId }}</p>
            @endif
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showFormModal', false)" />
            <x-button text="Save" icon="document-check" color="blue" wire:click="save" />
        </x-slot:footer>
    </x-modal>
</div>
