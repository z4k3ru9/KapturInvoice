<?php

namespace App\Filament\Resources\Credits\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CreditForm
{
    // company_id is set automatically from the active Filament tenant
    // (Credit::company()) — client/invoice relationship() selects are
    // tenant-scoped via App\Models\Concerns\BelongsToCompany.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->required(),
                Select::make('invoice_id')
                    ->label('Applied to invoice')
                    ->relationship('invoice', 'number')
                    ->searchable(),
                TextInput::make('number')
                    ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('credit_date'),
                TextInput::make('legacy_credit_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja credit id, for import traceability.'),
                Textarea::make('public_notes')
                    ->columnSpanFull(),
                Textarea::make('private_notes')
                    ->columnSpanFull(),
            ]);
    }
}
