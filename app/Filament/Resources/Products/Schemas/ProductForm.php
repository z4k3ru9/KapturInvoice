<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                TextInput::make('sku')
                    ->label('SKU'),
                TextInput::make('unit_cost')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('default_tax_rate_id')
                    ->label('Default tax rate')
                    ->relationship('defaultTaxRate', 'name'),
                TextInput::make('legacy_product_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja product id, for import traceability.'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
