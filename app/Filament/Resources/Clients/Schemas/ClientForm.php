<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    // company_id is set automatically from the active Filament tenant
    // (Client::company()) — no field needed here.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email(),
                        TextInput::make('phone')
                            ->tel(),
                        TextInput::make('website')
                            ->url(),
                        Select::make('currency_code')
                            ->label('Currency')
                            ->options(fn () => Currency::query()->pluck('code', 'code'))
                            ->searchable(),
                        TextInput::make('tax_number'),
                        TextInput::make('id_number'),
                        TextInput::make('legacy_client_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja client id, for import traceability.'),
                    ]),
                Section::make('Billing defaults')
                    ->description('Prefilled onto new invoices for this client (editable per invoice).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_discount')
                            ->label('Default discount')
                            ->numeric()
                            ->default(0),
                        Toggle::make('default_discount_is_percentage')
                            ->label('Discount is a percentage'),
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
                Section::make('Ledger')
                    ->description('Recomputed from invoices/payments/credits — not directly editable.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('balance')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('paid_to_date')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
