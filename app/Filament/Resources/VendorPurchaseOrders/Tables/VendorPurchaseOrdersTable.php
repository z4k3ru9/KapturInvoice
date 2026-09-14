<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Tables;

use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Enums\VendorPurchaseOrderStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\VendorPurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use RuntimeException;

class VendorPurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('vendor.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('po_date')->date()->sortable(),
                TextColumn::make('total')->numeric()->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DownloadPdfAction::vendorPurchaseOrder(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (VendorPurchaseOrder $record) => $record->status->canTransitionTo(VendorPurchaseOrderStatus::Approved))
                    ->requiresConfirmation()
                    ->action(function (VendorPurchaseOrder $record) {
                        try {
                            app(ApproveVendorPurchaseOrder::class)->approve($record);

                            Notification::make()->success()->title('Vendor purchase order approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not approve vendor purchase order')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
