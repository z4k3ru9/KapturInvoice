<?php

namespace App\Filament\Resources\PaymentGateways\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentGatewayForm
{
    // company_id is set automatically from the active Filament tenant
    // (PaymentGateway::company()). `config` holds API credentials and is
    // stored via an `encrypted` cast — see the model.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gateway')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->helperText('e.g. "Stripe (cards)" — a friendly label, since one driver can be configured more than once.'),
                        Select::make('driver')
                            ->required()
                            ->options([
                                'stripe' => 'Stripe',
                                'midtrans' => 'Midtrans',
                                'xendit' => 'Xendit',
                                'paypal' => 'PayPal',
                                'manual' => 'Manual / offline',
                            ]),
                        Toggle::make('is_enabled'),
                    ]),
                Section::make('Credentials')
                    ->description('Stored encrypted at rest.')
                    ->schema([
                        TextInput::make('config')
                            ->label('API credentials')
                            ->password()
                            ->revealable(),
                    ]),
                Section::make('Checkout options')
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('accepted_credit_cards')
                            ->options([
                                'visa' => 'Visa',
                                'mastercard' => 'Mastercard',
                                'amex' => 'American Express',
                                'jcb' => 'JCB',
                            ])
                            ->columnSpanFull(),
                        Toggle::make('show_address'),
                        Toggle::make('require_cvv'),
                    ]),
                Section::make('Surcharge fee')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fee_amount')->numeric()->default(0),
                        TextInput::make('fee_percent')->numeric()->default(0)->suffix('%'),
                        TextInput::make('fee_tax_name'),
                        TextInput::make('fee_tax_rate')->numeric()->suffix('%'),
                    ]),
                TextInput::make('legacy_account_gateway_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja account_gateway id, for import traceability.'),
            ]);
    }
}
