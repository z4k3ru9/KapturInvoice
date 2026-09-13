<?php

namespace App\Filament\Resources\VendorBills\Schemas;

use App\Models\VendorBill;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VendorBillInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')->placeholder('-'),
                TextEntry::make('vendor.name')->label('Vendor'),
                TextEntry::make('vendorPurchaseOrder.number')->label('Vendor purchase order')->placeholder('-'),
                TextEntry::make('status')->badge(),
                TextEntry::make('bill_date')->date()->placeholder('-'),
                TextEntry::make('due_date')->date()->placeholder('-'),
                TextEntry::make('total')->numeric(),
                TextEntry::make('amount_paid')->numeric(),
                TextEntry::make('balance')->numeric(),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('-'),
                TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (VendorBill $record): bool => $record->trashed()),
            ]);
    }
}
