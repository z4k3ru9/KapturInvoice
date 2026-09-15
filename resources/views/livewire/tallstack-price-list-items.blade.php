<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Price List Items']]" title="Price List Items">
        <x-slot:badge>
            <x-badge text="Vendor reference sheet (read-only)" color="gray" sm />
        </x-slot:badge>
        <x-slot:actions>
            {{-- Prominent primary action per prompt 14's own spec ("a
                 prominent 'Import pricelist' primary button"). color="blue",
                 not "primary" — same general-function-button convention as
                 every other TALL-stack page. --}}
            <x-button text="Import pricelist" icon="arrow-up-tray" color="blue" sm class="h-9" wire:click="openImportModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-3">
        A reference catalog kept separate from your sellable Products — browse the vendor's current price sheet, then create or refresh a real Product from a chosen row.
    </p>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Brand filter pills — real distinct brand values from
                     imported data, not an invented fixed list. --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="filterBrand(null)"
                            class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $brandFilter === null ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                        All brands
                    </button>
                    @foreach ($brands as $brand)
                        <button type="button" wire:click="filterBrand('{{ $brand }}')"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $brandFilter === $brand ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                            {{ $brand }}
                        </button>
                    @endforeach
                </div>

                <div class="w-full sm:w-72">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search SKU or description…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        @if (! $hasAnyItems)
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <x-icon name="archive-box" class="w-10 h-10 text-gray-300 dark:text-gray-700" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No pricelist imported yet — Import pricelist to get started.</p>
                <x-button text="Import pricelist" icon="arrow-up-tray" color="blue" sm wire:click="openImportModal" />
            </div>
        @else
            <x-table :headers="[
                ['index' => 'brand', 'label' => 'Brand'],
                ['index' => 'category', 'label' => 'Category'],
                ['index' => 'sku', 'label' => 'SKU'],
                ['index' => 'description', 'label' => 'Description'],
                ['index' => 'price', 'label' => 'List price', 'align' => 'right'],
                ['index' => 'updated_relative', 'label' => 'Last updated'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$items" paginate loading>
                {{-- Brand badge/logo-style chip (prompt 14: "Brand
                     (badge/logo-style chip, e.g. 'Hikvision')"). --}}
                @interact('column_brand', $row)
                    <x-badge text="{{ $row['brand'] }}" color="blue" sm />
                @endinteract

                {{-- Hand-editable field on this otherwise read-only
                     register — see TallStackPriceListItems's own docblock
                     for why. The importer's own header-detection guesses
                     this per sheet and is often wrong/blank. --}}
                @interact('column_category', $row)
                    <div class="flex items-center gap-1.5">
                        @if ($row['category'])
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $row['category'] }}</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                        <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-7 w-7" tooltip="Edit category" wire:click="openEditCategory({{ $row['id'] }})" />
                    </div>
                @endinteract

                @interact('column_sku', $row)
                    <span class="font-mono text-xs font-medium text-gray-800 dark:text-gray-100">{{ $row['sku'] }}</span>
                @endinteract

                {{-- Truncated per prompt 14's own spec. --}}
                @interact('column_description', $row)
                    <span class="text-xs text-gray-600 dark:text-gray-300 line-clamp-1 max-w-md block" title="{{ $row['description'] }}">
                        {{ $row['description'] ? \Illuminate\Support\Str::limit($row['description'], 80) : '—' }}
                    </span>
                @endinteract

                {{-- Right-aligned, tabular numerals per prompt 14's own spec. --}}
                @interact('column_price', $row)
                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-100" style="font-variant-numeric: tabular-nums;">{{ $row['price'] }}</span>
                @endinteract

                @interact('column_updated_relative', $row)
                    <span class="text-xs text-gray-400">{{ $row['updated_relative'] }}</span>
                @endinteract

                {{-- "Linked" badge on rows that already have a product_id,
                     plus a distinct button style from a normal row-edit
                     action (color="gray"/outline "sync" icon, never the
                     pencil "edit" look this app reserves for editing THIS
                     row) for "Create/update product", which writes to a
                     different model entirely. Both share a fixed h-9
                     height so the badge and button align as one cluster
                     instead of the badge's shorter natural line-height
                     sitting noticeably lower than the button. --}}
                @interact('column_actions', $row)
                    <div class="flex items-center justify-end gap-2">
                        @if ($row['linked'])
                            <x-badge text="Linked" color="green" sm class="h-9 flex items-center" />
                        @endif
                        <x-button text="{{ $row['linked'] ? 'Update product' : 'Create product' }}" icon="arrow-path" sm color="gray" class="h-9" wire:click="syncProduct({{ $row['id'] }})" />
                    </div>
                @endinteract

                <x-slot:empty>No pricelist items match this filter.</x-slot:empty>
            </x-table>
        @endif
    </x-card>

    {{-- Import modal — field-for-field ListPriceListItems's own "Import
         pricelist" header action (App\Services\PriceListImporter), never
         inventing fields it doesn't take (e.g. the mockup's decorative
         "Hardware Class"/"Auto-update linked products" controls have no
         backing service parameter and are left out). --}}
    <x-modal wire="showImportModal" title="Import vendor pricelist" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="importBrand" label="Brand" required
                hint="Tags every imported row, e.g. &quot;Hikvision&quot; or &quot;HiLook&quot;." />

            <x-upload wire:model="file" accept=".xlsx" tip="Vendor pricelist (.xlsx)." />

            <div class="rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 px-3 py-2 flex items-start gap-2">
                <x-icon name="information-circle" class="w-4 h-4 shrink-0 mt-0.5 text-blue-500" />
                <p class="text-xs text-blue-700 dark:text-blue-300">
                    Files are matched by SKU — re-uploading a revised sheet refreshes existing rows instead of duplicating them.
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showImportModal', false)" />
            <x-button text="Import" icon="arrow-up-tray" color="blue" wire:click="import" />
        </x-slot:footer>
    </x-modal>

    {{-- Edit-category modal — the one hand-editable field on this
         otherwise import-only register, see TallStackPriceListItems's own
         docblock. Reuses <x-tallstack.category-select> (repair plan Phase
         10a's reusable searchable/inline-create picker), sourced from
         every distinct category already used on this company's price
         list items. --}}
    <x-modal wire="showCategoryModal" title="Edit category" center="sm">
        <x-tallstack.category-select wire:model="category" :options="$categories" />

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showCategoryModal', false)" />
            <x-button text="Save" icon="check" color="blue" wire:click="saveCategory" />
        </x-slot:footer>
    </x-modal>
</div>
