<?php

namespace App\Filament\Resources\TaxRates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TaxRateForm
{
    // company_id is set automatically from the active Filament tenant
    // (TaxRate::company()) — no field needed here.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                Toggle::make('is_inclusive')
                    ->helperText('Whether this rate is already included in the price, rather than added on top.'),
                TextInput::make('legacy_tax_rate_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja tax_rate id, for import traceability.'),
            ]);
    }
}
