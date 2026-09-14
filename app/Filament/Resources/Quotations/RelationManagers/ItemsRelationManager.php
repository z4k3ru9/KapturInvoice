<?php

namespace App\Filament\Resources\Quotations\RelationManagers;

use App\Models\Product;
use App\Services\QuotationTotalsCalculator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Create quotations from catalog or custom lines" — Specs.md. Mirrors
 * App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager's
 * bounded/server-searched product picker (see that class's docblock for
 * why an eager options() closure is a real bug class, not a style choice).
 */
class ItemsRelationManager extends RelationManager
{
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
                TextInput::make('discount')->numeric()->default(0),
                Toggle::make('discount_is_percentage')->label('Discount is a percentage'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa):
            // drag/keyboard reorder, persisted in one batched write;
            // preserves deliberate row order in the printed Quotation.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unit_cost')->numeric(),
                TextColumn::make('line_total')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()->after(fn () => $this->recalculate()),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => $this->recalculate()),
                DeleteAction::make()->after(fn () => $this->recalculate()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(fn () => $this->recalculate()),
                ]),
            ]);
    }

    protected function recalculate(): void
    {
        app(QuotationTotalsCalculator::class)->recalculate($this->getOwnerRecord());
    }
}
