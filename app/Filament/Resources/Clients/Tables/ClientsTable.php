<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Actions\Reports\GenerateStatementOfAccount;
use App\Models\Client;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('currency_code')
                    ->label('Currency'),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('paid_to_date')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                static::previewStatementOfAccountAction(),
                static::generateStatementOfAccountAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Phase 06B Slice 1 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
     * "A preview may be regenerated before generation." Computes via
     * App\Services\Reports\BuildStatementOfAccount on every request
     * (App\Http\Controllers\StatementOfAccountPreviewController) and is
     * never persisted — no `App\Models\StatementOfAccount` row, no
     * document number.
     */
    private static function previewStatementOfAccountAction(): Action
    {
        return Action::make('previewStatementOfAccount')
            ->label('Preview Statement of Account')
            ->icon('heroicon-o-eye')
            ->schema(static::statementOfAccountPeriodSchema())
            ->action(function (Client $record, array $data) {
                $url = route('statement-of-accounts.preview', [
                    'client' => $record->getKey(),
                    'period_start' => Carbon::parse($data['period_start'])->toDateString(),
                    'period_end' => Carbon::parse($data['period_end'])->toDateString(),
                ]);

                Notification::make()
                    ->success()->seconds(4)
                    ->title('Statement of Account preview ready')
                    ->body($url)
                    ->send();
            });
    }

    /**
     * "A generated or sent SOA preserves an immutable PDF snapshot." —
     * same Specs.md. Persists a numbered, frozen
     * App\Models\StatementOfAccount row via
     * App\Actions\Reports\GenerateStatementOfAccount.
     */
    private static function generateStatementOfAccountAction(): Action
    {
        return Action::make('generateStatementOfAccount')
            ->label('Generate Statement of Account')
            ->icon('heroicon-o-document-text')
            ->schema(static::statementOfAccountPeriodSchema())
            ->action(function (Client $record, array $data) {
                $statementOfAccount = app(GenerateStatementOfAccount::class)->generate(
                    $record,
                    Carbon::parse($data['period_start']),
                    Carbon::parse($data['period_end']),
                    Auth::user(),
                );

                Notification::make()
                    ->success()->seconds(4)
                    ->title('Statement of Account generated')
                    ->body(route('statement-of-accounts.pdf', $statementOfAccount))
                    ->send();
            });
    }

    /** @return array<int, DatePicker> */
    private static function statementOfAccountPeriodSchema(): array
    {
        return [
            DatePicker::make('period_start')
                ->label('Period start')
                ->required()
                ->default(now()->startOfMonth())
                ->native(false),
            DatePicker::make('period_end')
                ->label('Period end')
                ->required()
                ->default(now()->endOfMonth())
                ->afterOrEqual('period_start')
                ->native(false),
        ];
    }
}
