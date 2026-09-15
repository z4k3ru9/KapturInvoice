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
use Filament\Support\Icons\Heroicon;
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
            ->icon(Heroicon::OutlinedEye)
            ->schema(static::statementOfAccountPeriodSchema())
            ->action(function (Client $record, array $data) {
                $url = route('statement-of-accounts.preview', [
                    'client' => $record->getKey(),
                    'period_start' => Carbon::parse($data['period_start'])->toDateString(),
                    'period_end' => Carbon::parse($data['period_end'])->toDateString(),
                ]);

                // Codex review finding on PR #4: a raw URL in a
                // 4-second-then-gone notification body made the user
                // manually copy it before it vanished. A persistent
                // notification carrying a real clickable "Open preview"
                // action opens the PDF directly instead.
                Notification::make()
                    ->success()->persistent()
                    ->title('Statement of Account preview ready')
                    ->actions([
                        Action::make('open')
                            ->label('Open preview')
                            ->url($url)
                            ->openUrlInNewTab(),
                    ])
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
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema(static::statementOfAccountPeriodSchema())
            ->action(function (Client $record, array $data) {
                $statementOfAccount = app(GenerateStatementOfAccount::class)->generate(
                    $record,
                    Carbon::parse($data['period_start']),
                    Carbon::parse($data['period_end']),
                    Auth::user(),
                );

                // Same fix as the preview action above — a clickable,
                // persistent "Open PDF" action instead of a raw URL that
                // vanishes with the notification. The generated statement
                // is also always reopenable later from the client's own
                // Statements of Account relation manager (it's a real
                // persisted row, unlike a preview).
                Notification::make()
                    ->success()->persistent()
                    ->title('Statement of Account generated')
                    ->body("Number: {$statementOfAccount->number}")
                    ->actions([
                        Action::make('open')
                            ->label('Open PDF')
                            ->url(route('statement-of-accounts.pdf', $statementOfAccount))
                            ->openUrlInNewTab(),
                    ])
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
