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
use Illuminate\Database\Eloquent\Collection;
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

                            Notification::make()->success()->seconds(4)->title('Vendor purchase order approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not approve vendor purchase order')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    static::forceDeleteBulkAction(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Codex review finding on PR #4: Filament's stock ForceDeleteBulkAction
     * only hides itself while the Trashed filter isn't set — once
     * switched to "With Trashed"/"Only Trashed", any selected row
     * (regardless of its own status) gets `forceDelete()`'d directly, and
     * the new foreign keys cascade through this PO's bills, their vendor
     * payments, and those payments' receipts/reversal/amendment
     * evidence — physically erasing the payable audit trail
     * FINALIZED-DECISIONS.md §1 requires never be deleted once issued.
     * Restricted here to Draft, never-billed-against rows only.
     */
    private static function forceDeleteBulkAction(): ForceDeleteBulkAction
    {
        return ForceDeleteBulkAction::make()
            ->action(function (Collection $records) {
                $blocked = 0;

                foreach ($records as $record) {
                    if ($record->status !== VendorPurchaseOrderStatus::Draft || $record->bills()->exists()) {
                        $blocked++;

                        continue;
                    }

                    $record->forceDelete();
                }

                if ($blocked > 0) {
                    Notification::make()
                        ->warning()->persistent()
                        ->title('Some vendor purchase orders were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a Draft PO with no vendor bills raised against it can be permanently deleted.")
                        ->send();
                }
            });
    }
}
