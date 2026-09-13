<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\Quotation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class QuotationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')->label('Client'),
                TextEntry::make('number')->placeholder('-'),
                TextEntry::make('status')->badge(),
                TextEntry::make('pricing_mode')->badge(),
                TextEntry::make('quotation_date')->date()->placeholder('-'),
                TextEntry::make('valid_until')->date()->placeholder('-'),
                TextEntry::make('discount')->numeric(),
                IconEntry::make('discount_is_percentage')->boolean(),
                TextEntry::make('subtotal')->numeric(),
                TextEntry::make('total')->numeric(),
                TextEntry::make('customer_po_number')
                    ->label('Customer PO / COC number')
                    ->placeholder('-'),
                TextEntry::make('customer_po_date')->date()->placeholder('-'),
                IconEntry::make('customer_po_is_system_generated')
                    ->label('System-generated COC')
                    ->boolean(),
                TextEntry::make('salesOrder.number')
                    ->label('Job')
                    ->placeholder('-'),
                TextEntry::make('terms')->placeholder('-')->columnSpanFull(),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('sent_at')->dateTime()->placeholder('-'),
                TextEntry::make('accepted_at')->dateTime()->placeholder('-'),
                TextEntry::make('rejected_at')->dateTime()->placeholder('-'),
                TextEntry::make('expired_at')->dateTime()->placeholder('-'),
                TextEntry::make('cancelled_at')->dateTime()->placeholder('-'),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Quotation $record): bool => $record->trashed()),
            ]);
    }
}
