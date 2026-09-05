<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    // company_id is set automatically from the active Filament tenant
    // (Vendor::company()) — no field needed here.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->columnSpanFull(),
                        TextInput::make('email')->label('Email address')->email(),
                        TextInput::make('phone')->tel(),
                        TextInput::make('website')->url(),
                        TextInput::make('legacy_vendor_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja vendor id, for import traceability.'),
                    ]),
                Section::make('Address')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address_line_1')->columnSpanFull(),
                        TextInput::make('address_line_2')->columnSpanFull(),
                        TextInput::make('city'),
                        TextInput::make('state'),
                        TextInput::make('postal_code'),
                        TextInput::make('country_code')->label('Country code')->maxLength(2),
                    ]),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
