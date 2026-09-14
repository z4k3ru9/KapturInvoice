<?php

namespace App\Filament\Resources\VendorPurchaseOrders\RelationManagers;

use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Product;
use App\Services\Procurement\VendorPurchaseOrderTotalsCalculator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Mirrors App\Filament\Resources\Quotations\RelationManagers\ItemsRelationManager's
 * bounded/server-searched product picker. No discount fields —
 * App\Models\VendorPurchaseOrderItem has none — the line is simply
 * `quantity * unit_cost`, recomputed by
 * App\Services\Procurement\VendorPurchaseOrderTotalsCalculator after every
 * create/edit/delete.
 */
class ItemsRelationManager extends RelationManager
{
    /**
     * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
     * a real bug found via browser testing: Filament's relation-manager
     * lazy loading (Filament\Support\Concerns\CanBeLazy, `$isLazy = true`
     * by default) never actually initializes on a genuine full page load
     * (only on Livewire's own `wire:navigate` soft navigation) — the tab
     * gets stuck showing its "Loading..." placeholder forever, with no
     * Livewire request ever firing to mount it. Same root cause already
     * documented for the three dashboard widgets in CLAUDE.md/
     * docs/filament-admin-layout-design.md §9 (a Filament/Livewire lazy-
     * loading bug, not this app's) — same fix: turn lazy loading off.
     */
    protected static bool $isLazy = false;

    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set) {
                        if ($product = Product::find($state)) {
                            $set('title', $product->name);
                            $set('unit_cost', $product->unit_cost);
                        }
                    }),
                TextInput::make('title')->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('quantity')->numeric()->default(1)->required(),
                TextInput::make('unit_cost')->numeric()->default(0)->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa):
            // drag/keyboard reorder, persisted in one batched write;
            // preserves deliberate row order in the printed Vendor PO.
            // Restricted to Draft owners — the PO's own `total` is
            // immutable once approved (this class's own docblock), so
            // mutating its lines afterward would silently desync that
            // frozen total from what the printed document actually lists
            // (a Codex review finding on PR #4).
            ->reorderable('sort_order', fn (): bool => $this->getOwnerRecord()->status === VendorPurchaseOrderStatus::Draft)
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unit_cost')->numeric(),
                TextColumn::make('line_total')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === VendorPurchaseOrderStatus::Draft)
                    ->after(fn () => $this->recalculate()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === VendorPurchaseOrderStatus::Draft)
                    ->after(fn () => $this->recalculate()),
                DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === VendorPurchaseOrderStatus::Draft)
                    ->after(fn () => $this->recalculate()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->status === VendorPurchaseOrderStatus::Draft)
                        ->after(fn () => $this->recalculate()),
                ]),
            ]);
    }

    protected function recalculate(): void
    {
        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($this->getOwnerRecord());
    }
}
