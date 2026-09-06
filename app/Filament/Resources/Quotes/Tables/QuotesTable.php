<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\InvoiceStatus;
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
                                ->success()
                                ->title('Quote sent')
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->danger()
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
                            ->success()
                            ->title('Converted to invoice')
                            ->body("Created invoice #{$invoice->id}.")
                            ->send();
                    }),
                DownloadPdfAction::invoice(),
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
