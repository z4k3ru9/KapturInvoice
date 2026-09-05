<?php

namespace App\Filament\Resources\PaymentGateways\Schemas;

use App\Enums\LocalPaymentMethod;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentGatewayForm
{
    // company_id is set automatically from the active Filament tenant
    // (PaymentGateway::company()). `config` holds API credentials/details
    // and is a structured array stored via an `encrypted:array` cast —
    // see the model. Only 'local_api' has a real driver behind it yet
    // (App\Services\PaymentGateways\PaymentGatewayManager) — the others
    // remain config-only placeholders.
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
                            ->live()
                            ->options([
                                'local_api' => 'Local API (Indonesia)',
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
                        TextInput::make('config.api_key')
                            ->label('API key / secret')
                            ->password()
                            ->revealable(),
                    ]),
                Section::make('Local API (Indonesia) connection')
                    ->description('Config for App\Services\PaymentGateways\LocalApiPaymentGatewayDriver — swap the endpoint once the real provider is confirmed.')
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('driver') === 'local_api')
                    ->schema([
                        TextInput::make('config.base_url')
                            ->label('API base URL')
                            ->url()
                            ->placeholder('https://api.example-id-gateway.test')
                            ->required(fn (Get $get) => $get('driver') === 'local_api'),
                        TextInput::make('config.merchant_id')
                            ->label('Merchant ID'),
                        CheckboxList::make('config.methods')
                            ->label('Enabled payment methods')
                            ->options(LocalPaymentMethod::class)
                            ->columnSpanFull(),
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
