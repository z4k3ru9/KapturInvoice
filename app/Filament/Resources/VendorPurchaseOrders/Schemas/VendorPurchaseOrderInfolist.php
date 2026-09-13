<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Schemas;

use App\Models\VendorPurchaseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VendorPurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')->placeholder('-'),
                TextEntry::make('vendor.name')->label('Vendor'),
                TextEntry::make('status')->badge(),
                TextEntry::make('po_date')->date()->placeholder('-'),
                TextEntry::make('due_date')->date()->placeholder('-'),
                TextEntry::make('delivery_date')->date()->placeholder('-'),
                TextEntry::make('total')->numeric(),
                TextEntry::make('terms')->placeholder('-')->columnSpanFull(),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                TextEntry::make('cancelled_at')->dateTime()->placeholder('-'),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (VendorPurchaseOrder $record): bool => $record->trashed()),
            ]);
    }
}
