<?php

namespace App\Filament\Resources\RecurringInvoices\Tables;

use App\Filament\Support\DownloadPdfAction;
use App\Models\Invoice;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RecurringInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')->searchable()->sortable(),
                TextColumn::make('recurring_frequency')->badge(),
                TextColumn::make('recurring_start_date')->date()->sortable(),
                TextColumn::make('recurring_end_date')->date()->sortable(),
                TextColumn::make('recurring_last_sent_at')->dateTime()->sortable(),
                IconColumn::make('auto_bill')->boolean(),
                TextColumn::make('generated_invoices_count')->counts('generatedInvoices')->label('Generated'),
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
                Action::make('generateNow')
                    ->label('Generate now')
                    ->icon(Heroicon::OutlinedBolt)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        $invoice = app(InvoiceDuplicator::class)->generateRecurringInstance($record);

                        Notification::make()
                            ->success()->seconds(4)
                            ->title('Invoice generated')
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
