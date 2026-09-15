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
use Illuminate\Database\Eloquent\Collection;

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
                    static::forceDeleteBulkAction(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * docs/REFACTOR_PLAN.md drift audit: same stock-ForceDeleteBulkAction
     * gap already fixed on every other Sales & Billing resource in this
     * domain (Invoices/Quotes/Quotations/SalesOrders/Credits/Payments) —
     * this template row was the one left unguarded. Force-deleting a
     * recurring template that has already generated real invoices would
     * leave those invoices' `recurring_template_id` pointing at nothing,
     * so this is restricted to a template with no generated invoices.
     */
    public static function forceDeleteBulkAction(): ForceDeleteBulkAction
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
                        ->title('Some recurring invoices were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a recurring template with no generated invoices can be permanently deleted.")
                        ->send();
                }
            });
    }

    public static function isSafeToForceDelete(Invoice $record): bool
    {
        return ! $record->generatedInvoices()->exists();
    }
}
