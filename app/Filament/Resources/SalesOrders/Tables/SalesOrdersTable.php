<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Actions\Sales\ApproveSalesOrder;
use App\Actions\Sales\TransitionSalesOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use RuntimeException;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('client.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('approved_value')->numeric()->sortable(),
                TextColumn::make('quotation.number')->label('Source quotation'),
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
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(ApproveSalesOrder::class)->approve($record);

                            Notification::make()->success()->title('Job approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not approve job')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('advance')
                    ->label('Advance status')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->visible(fn (SalesOrder $record) => collect($record->status->allowedNextStates())
                        ->reject(fn (SalesOrderStatus $s) => $s === SalesOrderStatus::Cancelled)
                        ->isNotEmpty())
                    ->schema(fn (SalesOrder $record) => [
                        Select::make('to')
                            ->label('New status')
                            ->options(collect($record->status->allowedNextStates())
                                ->reject(fn (SalesOrderStatus $s) => $s === SalesOrderStatus::Cancelled)
                                ->mapWithKeys(fn (SalesOrderStatus $s) => [$s->value => $s->getLabel()]))
                            ->required(),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        try {
                            app(TransitionSalesOrderStatus::class)->transition($record, SalesOrderStatus::from($data['to']));

                            Notification::make()->success()->title('Job updated')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not update job')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (SalesOrder $record) => ! $record->status->isTerminal())
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(TransitionSalesOrderStatus::class)->transition($record, SalesOrderStatus::Cancelled);

                            Notification::make()->success()->title('Job cancelled')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not cancel job')->body($e->getMessage())->send();
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
