<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InvoiceForm
{
    // company_id is set automatically from the active Filament tenant
    // (Invoice::company()) — client/self relationship() selects are
    // tenant-scoped via App\Models\Concerns\BelongsToCompany.
    // subtotal/tax_total/total/balance are recomputed from the line items
    // by App\Services\InvoiceTotalsCalculator — not directly editable.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('type')
                            ->options(InvoiceType::class)
                            ->default(InvoiceType::Invoice)
                            ->required(),
                        Select::make('status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft)
                            ->required(),
                        TextInput::make('number')
                            ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                        TextInput::make('po_number'),
                        DatePicker::make('invoice_date'),
                        DatePicker::make('due_date'),
                        TextInput::make('currency_code'),
                        TextInput::make('discount')
                            ->numeric()
                            ->default(0),
                        Toggle::make('discount_is_percentage')
                            ->label('Discount is a percentage'),
                        TextInput::make('legacy_invoice_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja invoice id, for import traceability.'),
                    ]),
                Section::make('Recurring')
                    ->columns(2)
                    ->collapsed(fn (?Invoice $record) => ! $record?->is_recurring)
                    ->schema([
                        Toggle::make('is_recurring')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('recurring_frequency')
                            ->helperText('weekly, monthly, quarterly, yearly…')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        Toggle::make('auto_bill')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        DatePicker::make('recurring_start_date')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        DatePicker::make('recurring_end_date')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('terms')->columnSpanFull(),
                        Textarea::make('public_notes')->columnSpanFull(),
                        Textarea::make('private_notes')->columnSpanFull(),
                        Textarea::make('footer')->columnSpanFull(),
                    ]),
                Section::make('Totals')
                    ->description('Recomputed automatically from the line items below.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('tax_total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('balance')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }
}
