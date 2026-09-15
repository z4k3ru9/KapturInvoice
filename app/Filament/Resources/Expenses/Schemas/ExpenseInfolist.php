<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\Expense;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor & dates')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vendor.name')->label('Vendor')->placeholder('-'),
                        TextEntry::make('category.name')->label('Category')->placeholder('-'),
                        TextEntry::make('expense_date')->date()->placeholder('-'),
                        TextEntry::make('transaction_reference')->placeholder('-'),
                    ]),
                Section::make('Amounts')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subtotal')->label('Amount')->money(fn (Expense $record) => $record->currency_code),
                        TextEntry::make('taxes.name')->label('Taxes')->badge()->placeholder('-'),
                        TextEntry::make('tax_total')->money(fn (Expense $record) => $record->currency_code),
                        TextEntry::make('total')->money(fn (Expense $record) => $record->currency_code),
                    ]),
                Section::make('Rebilling')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('should_be_invoiced')->label('Rebill')->boolean(),
                        TextEntry::make('client.name')->label('Client')->placeholder('-'),
                        TextEntry::make('invoice.number')->label('Rebilled on invoice')->placeholder('-'),
                    ]),
                TextEntry::make('private_notes')->placeholder('-')->columnSpanFull(),
                Section::make('Record')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('legacy_expense_id')->numeric()->placeholder('-'),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->visible(fn (Expense $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
