<?php

namespace App\Filament\Resources\PriceListItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PriceListItemForm
{
    // company_id is set automatically from the active Filament tenant
    // (PriceListItem::company()) — no field needed here. `prices`/
    // `source_file`/`imported_at` aren't editable here either — they're
    // populated by the Import action, not hand-typed.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('brand')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU / Basic Model')
                    ->required(),
                TextInput::make('category'),
                TextInput::make('reference_price')
                    ->numeric(),
                TextInput::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
