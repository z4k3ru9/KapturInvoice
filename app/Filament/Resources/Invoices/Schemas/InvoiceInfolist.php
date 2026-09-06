<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')
                    ->label('Client'),
                TextEntry::make('legacy_invoice_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('po_number')
                    ->placeholder('-'),
                TextEntry::make('invoice_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('due_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('currency_code')
                    ->placeholder('-'),
                TextEntry::make('exchange_rate')
                    ->numeric(),
                TextEntry::make('discount')
                    ->numeric(),
                IconEntry::make('discount_is_percentage')
                    ->boolean(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('tax_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('amount_paid')
                    ->numeric(),
                TextEntry::make('balance')
                    ->numeric(),
                TextEntry::make('partial_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('partial_due_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('terms')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('public_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('private_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('footer')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_recurring')
                    ->boolean(),
                TextEntry::make('recurring_frequency')
                    ->placeholder('-'),
                TextEntry::make('recurring_start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('recurring_end_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('recurring_last_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('recurringTemplate.number')
                    ->label('Recurring template')
                    ->placeholder('-'),
                IconEntry::make('auto_bill')
                    ->boolean(),
                TextEntry::make('convertedFromQuote.number')
                    ->label('Converted from quote')
                    ->placeholder('-'),
                TextEntry::make('sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('viewed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Invoice $record): bool => $record->trashed()),
            ]);
    }
}
