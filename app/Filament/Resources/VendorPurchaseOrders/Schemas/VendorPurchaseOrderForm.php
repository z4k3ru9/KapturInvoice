<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `status` is deliberately not a form field — the only transition
 * (Draft -> Approved) goes through
 * App\Actions\Procurement\ApproveVendorPurchaseOrder via the table's
 * Approve action, never a bare edit (mirrors QuotationForm).
 */
class VendorPurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor purchase order')
                    ->columns(2)
                    ->schema([
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('number')
                            ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                        DatePicker::make('po_date'),
                        DatePicker::make('due_date'),
                        DatePicker::make('delivery_date'),
                    ]),
                Section::make('Terms & notes')
                    ->schema([
                        Textarea::make('terms')->columnSpanFull(),
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
                Section::make('Total')
                    ->description('Recomputed automatically from the line items below.')
                    ->schema([
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }
}
