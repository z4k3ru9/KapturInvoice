<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\InvoiceTotalsCalculator;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Line items, each optionally taxed by any number of the company's tax
 * rates — the normalized replacement for InvoiceNinja's inline
 * tax_name1/rate1 + tax_name2/rate2 columns (see
 * docs/invoiceninja-v4-schema-reference.md §1). `tax_rate_ids` here is a
 * virtual field: it isn't an invoice_items column, it drives
 * InvoiceTotalsCalculator::syncItemTaxes() in the create/edit actions below.
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
                    // Server-searched and result-bounded (Filament's
                    // relationship-mode Select, not an eagerly-loaded
                    // options() array) — see
                    // docs/rebuild/specs/02-parties-and-catalog/Specs.md's
                    // "search does not load unbounded records" requirement.
                    // A company's catalog can grow well past what's safe to
                    // ship to the browser on every form render.
                    ->relationship('product', 'name')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set) {
                        if ($product = Product::find($state)) {
                            $set('title', $product->name);
                            $set('unit_cost', $product->unit_cost);
                            $set('tax_rate_ids', $product->default_tax_rate_id ? [$product->default_tax_rate_id] : []);
                        }
                    }),
                TextInput::make('title')->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('quantity')->numeric()->default(1)->required(),
                TextInput::make('unit_cost')->numeric()->default(0)->required(),
                TextInput::make('discount')->numeric()->default(0),
                Toggle::make('discount_is_percentage')->label('Discount is a percentage'),
                Select::make('tax_rate_ids')
                    ->label('Taxes')
                    ->multiple()
                    ->options(fn () => $this->getOwnerRecord()->company->taxRates()->pluck('name', 'id'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa):
            // "Provide drag handles and keyboard reorder controls;
            // preserve deliberate row order in PDFs." Filament's own
            // reorder handle persists the new `sort_order` values in one
            // batched write (never per keystroke); items()/PDF views
            // already order by this column (App\Models\Invoice::items()).
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unit_cost')->numeric(),
                TextColumn::make('taxes.name')->badge()->label('Taxes'),
                TextColumn::make('line_total')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $taxRateIds = Arr::pull($data, 'tax_rate_ids', []);

                        /** @var InvoiceItem $item */
                        $item = $this->getRelationship()->create($data);
                        app(InvoiceTotalsCalculator::class)->syncItemTaxes($item, $taxRateIds);

                        return $item;
                    })
                    ->after(fn () => $this->recalculateInvoice()),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, InvoiceItem $record): array {
                        $data['tax_rate_ids'] = $record->taxes()->pluck('tax_rate_id')->filter()->values()->all();

                        return $data;
                    })
                    ->using(function (InvoiceItem $record, array $data): Model {
                        $taxRateIds = Arr::pull($data, 'tax_rate_ids', []);

                        $record->update($data);
                        app(InvoiceTotalsCalculator::class)->syncItemTaxes($record, $taxRateIds);

                        return $record;
                    })
                    ->after(fn () => $this->recalculateInvoice()),
                DeleteAction::make()
                    ->after(fn () => $this->recalculateInvoice()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => $this->recalculateInvoice()),
                ]),
            ]);
    }

    protected function recalculateInvoice(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getOwnerRecord();

        app(InvoiceTotalsCalculator::class)->recalculate($invoice);
    }
}
