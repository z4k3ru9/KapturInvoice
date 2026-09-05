<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')
                    ->label('Client'),
                TextEntry::make('invoice.number')
                    ->label('Invoice')
                    ->placeholder('-'),
                TextEntry::make('contact.name')
                    ->label('Contact')
                    ->placeholder('-'),
                TextEntry::make('legacy_payment_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('refunded_amount')
                    ->numeric(),
                TextEntry::make('currency_code')
                    ->placeholder('-'),
                TextEntry::make('exchange_rate')
                    ->numeric(),
                TextEntry::make('method')
                    ->placeholder('-'),
                TextEntry::make('gateway')
                    ->placeholder('-'),
                TextEntry::make('gateway_reference')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('last4')
                    ->placeholder('-'),
                TextEntry::make('payment_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Payment $record): bool => $record->trashed()),
            ]);
    }
}
