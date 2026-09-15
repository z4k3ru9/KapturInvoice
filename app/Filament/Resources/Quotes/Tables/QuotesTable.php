<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Support\DownloadPdfAction;
use App\Models\Invoice;
use App\Services\BillingMailer;
use App\Services\InvoiceDuplicator;
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

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('client.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('invoice_date')->label('Quote date')->date()->sortable(),
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
                Action::make('send')
                    ->label(fn (Invoice $record) => $record->status === InvoiceStatus::Draft ? 'Send' : 'Resend')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        try {
                            app(BillingMailer::class)->sendQuote($record);

                            Notification::make()
                                ->success()->seconds(4)
                                ->title('Quote sent')
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->danger()->persistent()
                                ->title('Could not send quote')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('convertToInvoice')
                    ->label('Convert to invoice')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        $invoice = app(InvoiceDuplicator::class)->convertQuoteToInvoice($record);

                        Notification::make()
                            ->success()->seconds(4)
                            ->title('Converted to invoice')
                            ->body("Created invoice #{$invoice->id}.")
                            ->send();
                    }),
                DownloadPdfAction::invoice(),
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
     * docs/REFACTOR_PLAN.md drift audit: same stock-ForceDeleteBulkAction
     * gap already fixed on VendorBill. Reuses InvoicesTable's guard (same
     * underlying `invoices` table/model) plus a Quote-specific check: a
     * quote already converted to a real invoice
     * (App\Services\InvoiceDuplicator) can never be permanently deleted.
     */
    public static function isSafeToForceDelete(Invoice $record): bool
    {
        return InvoicesTable::isSafeToForceDelete($record) && ! $record->convertedInvoices()->exists();
    }

    private static function forceDeleteBulkAction(): ForceDeleteBulkAction
    {
        return ForceDeleteBulkAction::make()
            ->action(function (Collection $records) {
                $blocked = 0;

                foreach ($records as $record) {
                    if (! static::isSafeToForceDelete($record)) {
                        $blocked++;

                        continue;
                    }

                    $record->forceDelete();
                }

                if ($blocked > 0) {
                    Notification::make()
                        ->warning()->persistent()
                        ->title('Some quotes were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a Draft quote with no payments, allocations, or converted invoice can be permanently deleted.")
                        ->send();
                }
            });
    }
}
