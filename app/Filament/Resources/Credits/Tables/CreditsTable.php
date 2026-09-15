<?php

namespace App\Filament\Resources\Credits\Tables;

use App\Filament\Support\DownloadPdfAction;
use App\Models\Credit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CreditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.number')
                    ->label('Applied to invoice')
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('credit_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
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
                DownloadPdfAction::credit(),
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
     * gap already fixed on VendorBill. A Credit has no status field —
     * `balance` differing from `amount` is the only signal it has been
     * applied to real invoice/payment history, so that is what this
     * guards on rather than a lifecycle state.
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
                        ->title('Some credits were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a credit never applied against an invoice/payment (balance still equal to its amount) can be permanently deleted.")
                        ->send();
                }
            });
    }

    public static function isSafeToForceDelete(Credit $record): bool
    {
        return (float) $record->balance === (float) $record->amount;
    }
}
