<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\CatalogItemType;
use App\Enums\TaxCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    // company_id is set automatically from the active Filament tenant
    // (Product::company()) — no field needed here.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(CatalogItemType::class)
                    ->default(CatalogItemType::Product)
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU'),
                FileUpload::make('image_path')
                    ->label('Picture')
                    ->image()
                    ->directory('products')
                    ->maxSize(5120)
                    ->helperText('Optional. Shown as a thumbnail on quotation line items and reusable in proposal snippets.'),
                TextInput::make('unit')
                    ->helperText('e.g. pcs, hour, package — not required for a service/labor line.'),
                TextInput::make('unit_cost')
                    ->label('Default price')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('tax_category')
                    ->options(TaxCategory::class)
                    ->default(TaxCategory::StandardTaxable)
                    ->required(),
                Select::make('default_tax_rate_id')
                    ->label('Default tax rate')
                    ->relationship('defaultTaxRate', 'name')
                    ->searchable(),
                Toggle::make('stock_flag')
                    ->label('Normally stocked')
                    ->helperText('A label only — this company does not track real inventory/availability.'),
                TextInput::make('legacy_product_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja product id, for import traceability.'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
