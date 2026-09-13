<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\SalesOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * A job is only ever created by App\Actions\Sales\CreateSalesOrderFromQuotation
 * — there is no create/edit form (see SalesOrderResource::getPages()), so
 * this infolist is the whole "view one job" surface. Per the acceptance
 * criteria in docs/rebuild/specs/03-sales-and-job/Specs.md ("A user can
 * open one job and find the accepted quote, PO state, milestones, ...
 * without navigating through unrelated legacy project screens"), the
 * quote/PO fields are surfaced directly here rather than requiring a
 * click-through to the quotation.
 */
class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')->placeholder('-'),
                TextEntry::make('client.name')->label('Client'),
                TextEntry::make('status')->badge(),
                TextEntry::make('approved_value')->numeric(),
                TextEntry::make('quotation.number')->label('Source quotation'),
                TextEntry::make('quotation.customer_po_number')->label('Customer PO / COC number')->placeholder('-'),
                TextEntry::make('quotation.customer_po_is_system_generated')
                    ->label('PO type')
                    ->formatStateUsing(fn (?bool $state) => $state ? 'System-generated COC' : 'Customer-supplied PO'),
                TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                TextEntry::make('operational_closed_at')->dateTime()->placeholder('-'),
                TextEntry::make('financial_closed_at')->dateTime()->placeholder('-'),
                TextEntry::make('cancelled_at')->dateTime()->placeholder('-'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (SalesOrder $record): bool => $record->trashed()),
            ]);
    }
}
