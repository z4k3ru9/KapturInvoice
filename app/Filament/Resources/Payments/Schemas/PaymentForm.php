<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use App\Models\Contact;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentForm
{
    // company_id is set automatically from the active Filament tenant
    // (Payment::company()) — client/invoice relationship() selects are
    // tenant-scoped via App\Models\Concerns\BelongsToCompany.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('invoice_id')
                    ->label('Applied to invoice')
                    ->relationship('invoice', 'number')
                    ->searchable(),
                Select::make('contact_id')
                    ->label('Contact')
                    ->options(fn (Get $get) => Contact::query()
                        ->where('client_id', $get('client_id'))
                        ->get()
                        ->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('refunded_amount')
                    ->numeric()
                    ->default(0),
                TextInput::make('currency_code'),
                Select::make('status')
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::Completed)
                    ->required(),
                TextInput::make('method')
                    ->helperText('card, bank_transfer, cash, …'),
                TextInput::make('gateway'),
                TextInput::make('gateway_reference'),
                TextInput::make('last4')
                    ->label('Card last 4')
                    ->maxLength(4),
                DatePicker::make('payment_date'),
                TextInput::make('legacy_payment_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja payment id, for import traceability.'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
