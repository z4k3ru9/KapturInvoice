<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('legacy_product_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sku')
                    ->label('SKU')
                    ->placeholder('-'),
                ImageEntry::make('image_path')
                    ->label('Picture')
                    ->imageSize(160)
                    ->square()
                    ->visible(fn (Product $record): bool => filled($record->image_path)),
                TextEntry::make('name'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('unit')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('unit_cost')
                    ->label('Default price')
                    ->money(fn () => Filament::getTenant()->currency_code),
                TextEntry::make('tax_category')
                    ->badge(),
                TextEntry::make('defaultTaxRate.name')
                    ->label('Default tax rate')
                    ->placeholder('-'),
                IconEntry::make('stock_flag')
                    ->label('Normally stocked')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Product $record): bool => $record->trashed()),
            ]);
    }
}
