<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\CatalogItemType;
use App\Enums\TaxCategory;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                // Plain upload, no crop tooling — square rendering is done
                // by the thumbnail column's object-cover, not by cropping
                // the source file (DESIGN §15).
                FileUpload::make('image_path')
                    ->label('Picture')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->imagePreviewHeight('120')
                    ->panelAspectRatio('1:1')
                    ->panelLayout('compact')
                    ->directory('products')
                    ->helperText('Optional. JPG, PNG, WEBP · max 2 MB.')
                    ->hintIcon(Heroicon::OutlinedInformationCircle)
                    ->hintIconTooltip('Shown as a thumbnail on quotation line items and reusable in proposal snippets.'),
                TextInput::make('unit')
                    ->helperText('e.g. pcs, hour, package — not required for a service/labor line.'),
                TextInput::make('unit_cost')
                    ->label('Default price')
                    ->prefix(fn () => Filament::getTenant()->currency_code)
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
                    ->searchable()
                    ->preload(),
                Toggle::make('stock_flag')
                    ->label('Normally stocked')
                    ->helperText('A label only — this company does not track real inventory/availability.'),
                Textarea::make('description')
                    ->helperText('Printed on quotations.')
                    ->columnSpanFull(),
                // Import traceability only — not part of the operational
                // catalog form (Stitch/§9 do not show it).
                Section::make('Import traceability')
                    ->collapsed()
                    ->components([
                        TextInput::make('legacy_product_id')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Legacy InvoiceNinja product id, for import traceability.'),
                    ])
                    ->visible(fn (?Product $record): bool => filled($record?->legacy_product_id)),
            ]);
    }
}
