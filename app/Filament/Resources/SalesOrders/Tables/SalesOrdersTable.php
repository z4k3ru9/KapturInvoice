<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Actions\Sales\ApproveSalesOrder;
use App\Actions\Sales\CloseJobFinancially;
use App\Actions\Sales\CloseJobOperationally;
use App\Actions\Sales\TransitionSalesOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
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
                DownloadPdfAction::salesOrder(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(ApproveSalesOrder::class)->approve($record);

                            Notification::make()->success()->seconds(4)->title('Job approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not approve job')->body($e->getMessage())->send();
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

                            Notification::make()->success()->seconds(4)->title('Job updated')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not update job')->body($e->getMessage())->send();
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

                            Notification::make()->success()->seconds(4)->title('Job cancelled')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not cancel job')->body($e->getMessage())->send();
                        }
                    }),

                // Phase 05 (docs/rebuild/specs/05-procurement-and-delivery):
                // operational and financial closure are independent — see
                // App\Models\SalesOrder::isFullyClosed().
                Action::make('closeOperationally')
                    ->label('Close operationally')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SalesOrder $record) => $record->operational_closed_at === null)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(CloseJobOperationally::class)->close($record);

                            Notification::make()->success()->seconds(4)->title('Job closed operationally')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not close job operationally')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('closeFinancially')
                    ->label('Close financially')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (SalesOrder $record) => $record->financial_closed_at === null)
                    ->schema([
                        Toggle::make('override')
                            ->label('Override outstanding-balance block')
                            ->live(),
                        Textarea::make('override_reason')
                            ->label('Override reason')
                            ->required()
                            ->visible(fn (Get $get) => (bool) $get('override'))
                            ->columnSpanFull(),
                        Textarea::make('outstanding_balance_summary')
                            ->label('Outstanding-balance summary')
                            ->required()
                            ->visible(fn (Get $get) => (bool) $get('override'))
                            ->columnSpanFull(),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        try {
                            app(CloseJobFinancially::class)->close(
                                $record,
                                Auth::user(),
                                (bool) ($data['override'] ?? false),
                                $data['override_reason'] ?? null,
                                $data['outstanding_balance_summary'] ?? null,
                            );

                            Notification::make()->success()->seconds(4)->title('Job closed financially')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not close job financially')->body($e->getMessage())->send();
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
