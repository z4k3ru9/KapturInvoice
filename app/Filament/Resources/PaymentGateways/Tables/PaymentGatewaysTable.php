<?php

namespace App\Filament\Resources\PaymentGateways\Tables;

use App\Models\PaymentGateway;
use App\Services\PaymentGateways\GatewayNotConfiguredException;
use App\Services\PaymentGateways\PaymentGatewayManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class PaymentGatewaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('driver')->badge(),
                IconColumn::make('is_enabled')->boolean(),
                TextColumn::make('fee_percent')->numeric()->suffix('%'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('testConnection')
                    ->label('Test connection')
                    ->icon(Heroicon::OutlinedBolt)
                    ->action(function (PaymentGateway $record) {
                        try {
                            $ok = app(PaymentGatewayManager::class)->driverFor($record)->testConnection();

                            $ok
                                ? Notification::make()->success()->title('Connected')->send()
                                : Notification::make()->danger()->title('Gateway responded, but the check failed')->send();
                        } catch (GatewayNotConfiguredException $e) {
                            Notification::make()->danger()->title('Not configured')->body($e->getMessage())->send();
                        } catch (ConnectionException $e) {
                            Notification::make()->danger()->title('Could not reach the gateway')->body($e->getMessage())->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('No driver for this gateway type')->body($e->getMessage())->send();
                        }
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
