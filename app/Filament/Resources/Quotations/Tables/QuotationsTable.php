<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\Quotation;
use App\Services\Sales\QuotationMailer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use RuntimeException;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('client.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('quotation_date')->date()->sortable(),
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
                DownloadPdfAction::quotation(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => self::transition($record, QuotationStatus::Approved)),
                Action::make('send')
                    ->label('Send')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Approved)
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => self::sendQuotation($record)),
                Action::make('accept')
                    ->label('Accept')
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Sent)
                    ->schema([
                        TextInput::make('customer_po_number')
                            ->label('Customer PO number')
                            ->helperText('Leave blank to generate an internal Customer Order Confirmation instead.'),
                        DatePicker::make('customer_po_date')->label('Customer PO date'),
                    ])
                    ->action(function (Quotation $record, array $data) {
                        try {
                            app(AcceptQuotation::class)->accept(
                                $record,
                                filled($data['customer_po_number'] ?? null) ? $data['customer_po_number'] : null,
                                filled($data['customer_po_date'] ?? null) ? Carbon::parse($data['customer_po_date']) : null,
                            );

                            Notification::make()->success()->seconds(4)->title('Quotation accepted')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not accept quotation')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Sent)
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => self::transition($record, QuotationStatus::Rejected)),
                Action::make('markExpired')
                    ->label('Mark expired')
                    ->icon(Heroicon::OutlinedClock)
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Sent)
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => self::transition($record, QuotationStatus::Expired)),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Quotation $record) => ! $record->status->isTerminal())
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => self::transition($record, QuotationStatus::Cancelled)),
                Action::make('createJob')
                    ->label('Create job')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->color('success')
                    ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Accepted && ! $record->salesOrder()->exists())
                    ->requiresConfirmation()
                    ->action(function (Quotation $record) {
                        try {
                            $salesOrder = app(CreateSalesOrderFromQuotation::class)->create($record);

                            Notification::make()
                                ->success()->seconds(4)
                                ->title('Job created')
                                ->body("Created job #{$salesOrder->number}.")
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not create job')->body($e->getMessage())->send();
                        }
                    }),
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
     * gap already fixed on VendorBill — restricted here to Draft
     * quotations with no Job created from them.
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
                        ->title('Some quotations were not force-deleted')
                        ->body("{$blocked} record(s) were skipped — only a Draft quotation with no job created from it can be permanently deleted.")
                        ->send();
                }
            });
    }

    public static function isSafeToForceDelete(Quotation $record): bool
    {
        return $record->status === QuotationStatus::Draft
            && ! $record->salesOrder()->exists();
    }

    private static function transition(Quotation $record, QuotationStatus $to): void
    {
        try {
            app(TransitionQuotationStatus::class)->transition($record, $to);

            Notification::make()->success()->seconds(4)->title('Quotation updated')->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->persistent()->title('Could not update quotation')->body($e->getMessage())->send();
        }
    }

    /**
     * The "Send" action's status transition (Approved -> Sent) stays a
     * separate, already-tested concern — this only adds the email
     * dispatch alongside it. If the transition itself fails, the email
     * is never attempted. If the transition succeeds but the email
     * fails (no contact email, mail transport error, etc.), the
     * quotation is still Sent — that failure surfaces as its own danger
     * notification rather than being silently swallowed or rolling back
     * the (correct, already-applied) status change.
     */
    private static function sendQuotation(Quotation $record): void
    {
        try {
            app(TransitionQuotationStatus::class)->transition($record, QuotationStatus::Sent);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->persistent()->title('Could not update quotation')->body($e->getMessage())->send();

            return;
        }

        try {
            app(QuotationMailer::class)->sendQuotation($record);

            Notification::make()->success()->seconds(4)->title('Quotation sent')->send();
        } catch (RuntimeException $e) {
            Notification::make()
                ->danger()->persistent()
                ->title('Quotation marked as sent, but the email could not be sent')
                ->body($e->getMessage())
                ->send();
        }
    }
}
