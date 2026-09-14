<?php

namespace App\Filament\Resources\Proposals\Tables;

use App\Enums\ProposalStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\Proposal;
use App\Services\ProposalConverter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class ProposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('client.name')->searchable()->sortable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount')->numeric()->sortable(),
                TextColumn::make('valid_until')->date()->sortable(),
                TextColumn::make('invoice.number')->label('Invoice')->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProposalStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DownloadPdfAction::proposal(),
                Action::make('markAccepted')
                    ->label('Mark accepted')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Proposal $record) => $record->status !== ProposalStatus::Accepted)
                    ->action(fn (Proposal $record) => $record->forceFill([
                        'status' => ProposalStatus::Accepted,
                        'responded_at' => now(),
                    ])->save()),
                Action::make('markDeclined')
                    ->label('Mark declined')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Proposal $record) => $record->status !== ProposalStatus::Declined)
                    ->action(fn (Proposal $record) => $record->forceFill([
                        'status' => ProposalStatus::Declined,
                        'responded_at' => now(),
                    ])->save()),
                Action::make('convertToInvoice')
                    ->label('Convert to invoice')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Proposal $record) => ! $record->invoice_id)
                    ->action(function (Proposal $record) {
                        try {
                            $invoice = app(ProposalConverter::class)->convertToInvoice($record);

                            Notification::make()
                                ->success()
                                ->title('Converted to invoice')
                                ->body("Created invoice {$invoice->number}.")
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Could not convert')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
