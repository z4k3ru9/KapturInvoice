<?php

namespace App\Filament\Resources\VendorBills\Schemas;

use App\Enums\VendorPurchaseOrderStatus;
use App\Models\VendorPurchaseOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * `status` is deliberately not a form field — every transition goes
 * through App\Actions\Procurement\{SubmitVendorBill,ApproveVendorBill} via
 * the table's Submit/Approve actions, never a bare edit (mirrors
 * QuotationForm). The Vendor PO picker is scoped to the selected vendor's
 * own Approved POs via a bounded `options()` closure rather than
 * `relationship()`, since it needs that extra vendor/status filter — the
 * query is bounded to one vendor's rows, not the unbounded-pluck bug class
 * from Phase 02.
 */
class VendorBillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor bill')
                    ->columns(2)
                    ->schema([
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('vendor_purchase_order_id')
                            ->label('Vendor purchase order')
                            ->options(fn (Get $get) => VendorPurchaseOrder::query()
                                ->where('vendor_id', $get('vendor_id'))
                                ->where('status', VendorPurchaseOrderStatus::Approved)
                                ->pluck('number', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('number')
                            ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                        DatePicker::make('bill_date'),
                        DatePicker::make('due_date'),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
                Section::make('Totals')
                    ->description('Recomputed automatically from the line items and payments below.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('amount_paid')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('balance')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }
}
