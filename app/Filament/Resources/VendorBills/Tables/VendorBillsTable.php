<?php

namespace App\Filament\Resources\VendorBills\Tables;

use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\RecordVendorPayment;
use App\Actions\Procurement\SubmitVendorBill;
use App\Enums\VendorBillStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\VendorBill;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class VendorBillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('vendor.name')->searchable()->sortable(),
                TextColumn::make('vendorPurchaseOrder.number')->label('Vendor PO'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->numeric()->sortable(),
                TextColumn::make('balance')->numeric()->sortable(),
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
                DownloadPdfAction::vendorBill(),
                Action::make('submit')
                    ->label('Submit')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (VendorBill $record) => $record->status === VendorBillStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (VendorBill $record) {
                        try {
                            app(SubmitVendorBill::class)->submit($record);

                            Notification::make()->success()->seconds(4)->title('Vendor bill submitted')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not submit vendor bill')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (VendorBill $record) => $record->status === VendorBillStatus::Submitted)
                    ->requiresConfirmation()
                    ->action(function (VendorBill $record) {
                        try {
                            app(ApproveVendorBill::class)->approve($record, Auth::user());

                            Notification::make()->success()->seconds(4)->title('Vendor bill approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not approve vendor bill')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (VendorBill $record) => in_array($record->status, [VendorBillStatus::Approved, VendorBillStatus::PartiallyPaid], true))
                    ->schema([
                        TextInput::make('amount')->numeric()->required(),
                        DatePicker::make('payment_date'),
                        TextInput::make('method')
                            ->helperText('bank_transfer, cheque, or manual.'),
                        TextInput::make('reference'),
                        FileUpload::make('proof_path')
                            ->label('Proof of payment')
                            ->directory('vendor-payment-proofs'),
                        Textarea::make('notes')->columnSpanFull(),
                    ])
                    ->action(function (VendorBill $record, array $data) {
                        try {
                            app(RecordVendorPayment::class)->record($record, $data);

                            Notification::make()->success()->seconds(4)->title('Vendor payment recorded')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not record vendor payment')->body($e->getMessage())->send();
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
     * the new foreign keys cascade through this bill's vendor payments,
     * their receipts, and reversal/amendment evidence — physically
     * erasing the payable audit trail FINALIZED-DECISIONS.md §1 requires
     * never be deleted once issued. Restricted here to Draft, never-paid
     * rows only.
     */
    private static function forceDeleteBulkAction(): ForceDeleteBulkAction
    {
        return ForceDeleteBulkAction::make()
            ->action(function (Collection $records) {
                $blocked = 0;

                foreach ($records as $record) {
                    if ($record->status !== VendorBillStatus::Draft || $record->payments()->exists()) {
                        $blocked++;

                        continue;
                    }

                    $record->forceDelete();
                }

                if ($blocked > 0) {
                    Notification::make()
                        ->warning()->persistent()
                        ->title('Some vendor bills were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a Draft bill with no recorded payments can be permanently deleted.")
                        ->send();
                }
            });
    }
}
