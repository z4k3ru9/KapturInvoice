<?php

namespace App\Filament\Resources\PriceListItems\Schemas;

use App\Models\PriceListItem;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PriceListItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('brand')
                    ->badge(),
                TextEntry::make('sku')
                    ->label('SKU / Basic Model'),
                TextEntry::make('category')
                    ->placeholder('-'),
                TextEntry::make('reference_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('prices')
                    ->label('All price tiers')
                    ->placeholder('-')
                    ->columnSpanFull()
                    ->formatStateUsing(function (PriceListItem $record): string {
                        if (blank($record->prices)) {
                            return '-';
                        }

                        return collect($record->prices)
                            ->map(fn ($amount, $label) => "{$label}: ".number_format((float) $amount, 2))
                            ->implode(' · ');
                    }),
                TextEntry::make('source_file')
                    ->label('Source file')
                    ->placeholder('-'),
                TextEntry::make('imported_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
