<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\TaxRate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExpenseForm
{
    // company_id is set automatically from the active Filament tenant
    // (Expense::company()) — vendor/category/client relationship() selects
    // are tenant-scoped via App\Models\Concerns\BelongsToCompany.
    // `tax_rate_ids` is a virtual field (not an expenses column, same
    // pattern as ItemsRelationManager's tax_rate_ids): it drives
    // App\Services\ExpenseTotalsCalculator::syncTaxes() in the create/edit
    // pages, the normalized replacement for the legacy inline
    // tax_name1/rate1 + tax_name2/rate2 columns (see
    // docs/invoiceninja-v4-schema-reference.md §2.5).
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense')
                    ->columns(2)
                    ->schema([
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable(),
                        Select::make('expense_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable(),
                        DatePicker::make('expense_date'),
                        TextInput::make('currency_code'),
                        TextInput::make('subtotal')
                            ->label('Amount')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Select::make('tax_rate_ids')
                            ->label('Taxes')
                            ->multiple()
                            ->options(fn () => TaxRate::query()->pluck('name', 'id')),
                        TextInput::make('transaction_reference'),
                        TextInput::make('legacy_expense_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja expense id, for import traceability.'),
                    ]),
                Section::make('Rebilling')
                    ->columns(2)
                    ->schema([
                        Toggle::make('should_be_invoiced')
                            ->label('Rebill to client')
                            ->live()
                            ->columnSpanFull(),
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->visible(fn (Get $get) => $get('should_be_invoiced')),
                        Select::make('invoice_id')
                            ->label('Rebilled on invoice')
                            ->relationship('invoice', 'number')
                            ->searchable()
                            ->visible(fn (Get $get) => $get('should_be_invoiced')),
                    ]),
                Section::make('Totals')
                    ->description('Recomputed automatically from the amount and taxes above.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tax_total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
                Textarea::make('private_notes')->columnSpanFull(),
            ]);
    }
}
