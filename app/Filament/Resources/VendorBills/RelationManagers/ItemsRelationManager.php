<?php

namespace App\Filament\Resources\VendorBills\RelationManagers;

use App\Actions\Procurement\AllocateJobCost;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Services\Procurement\VendorBillTotalsCalculator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

/**
 * Mirrors Quotations' ItemsRelationManager for the bounded/server-searched
 * product picker, plus a `vendor_purchase_order_item_id` picker bounded to
 * this bill's own PO's items (never an unbounded pluck — see
 * docs/rebuild/outputs/16-phase-02-checkpoint-report.md for why that's a
 * real bug class). Recomputes `line_total`/`vendor_bills.total` after
 * every create/edit/delete via
 * App\Services\Procurement\VendorBillTotalsCalculator, and surfaces each
 * line's unallocated remainder plus an "Allocate to job" row action
 * wrapping App\Actions\Procurement\AllocateJobCost — "show unallocated
 * remainder" (Specs.md).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set) {
                        if ($product = Product::find($state)) {
                            $set('title', $product->name);
                            $set('unit_cost', $product->unit_cost);
                        }
                    }),
                Select::make('vendor_purchase_order_item_id')
                    ->label('Vendor PO line')
                    ->options(function () {
                        /** @var VendorBill $bill */
                        $bill = $this->getOwnerRecord();

                        return $bill->vendorPurchaseOrder?->items->pluck('title', 'id') ?? [];
                    })
                    ->searchable(),
                TextInput::make('title')->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('quantity')->numeric()->default(1)->required(),
                TextInput::make('unit_cost')->numeric()->default(0)->required(),
                TextInput::make('net_amount')->numeric()->default(0)->required(),
                TextInput::make('tax_amount')->numeric()->default(0)->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa):
            // drag/keyboard reorder, persisted in one batched write;
            // preserves deliberate row order in the printed Vendor Bill.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unit_cost')->numeric(),
                TextColumn::make('net_amount')->numeric(),
                TextColumn::make('tax_amount')->numeric(),
                TextColumn::make('line_total')->numeric(),
                TextColumn::make('unallocated')
                    ->label('Unallocated')
                    ->state(fn (VendorBillItem $record) => $record->unallocatedAmount())
                    ->numeric(),
            ])
            ->headerActions([
                CreateAction::make()->after(fn () => $this->recalculate()),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => $this->recalculate()),
                DeleteAction::make()->after(fn () => $this->recalculate()),
                Action::make('allocateToJob')
                    ->label('Allocate to job')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->schema([
                        Select::make('sales_order_id')
                            ->label('Job')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => SalesOrder::query()
                                ->where('company_id', Filament::getTenant()?->id)
                                ->where('number', 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('number', 'id'))
                            ->getOptionLabelUsing(fn ($value) => SalesOrder::find($value)?->number),
                        TextInput::make('amount')->numeric()->required(),
                        TextInput::make('quantity')->numeric(),
                    ])
                    ->action(function (VendorBillItem $record, array $data) {
                        try {
                            $salesOrder = SalesOrder::findOrFail($data['sales_order_id']);

                            app(AllocateJobCost::class)->allocate(
                                $record,
                                $salesOrder,
                                (float) $data['amount'],
                                filled($data['quantity'] ?? null) ? (float) $data['quantity'] : null,
                            );

                            Notification::make()->success()->title('Cost allocated to job')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not allocate cost')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(fn () => $this->recalculate()),
                ]),
            ]);
    }

    protected function recalculate(): void
    {
        app(VendorBillTotalsCalculator::class)->recalculate($this->getOwnerRecord());
    }
}
