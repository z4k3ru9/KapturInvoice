<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Receivables\AllocateCustomerPayment;
use App\Actions\Receivables\AmendPaymentAllocation;
use App\Actions\Receivables\IssuePaymentReceipt;
use App\Actions\Receivables\ReverseCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\PaymentMethod;
use App\Filament\Support\DownloadPdfAction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingMailer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.number')
                    ->label('Invoice'),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('method')
                    ->searchable(),
                TextColumn::make('gateway')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('receipt.number')
                    ->label('Receipt')
                    ->placeholder('-'),
                TextColumn::make('payment_date')
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
                DownloadPdfAction::receipt(),
                Action::make('sendReceipt')
                    ->label('Send receipt')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        try {
                            app(BillingMailer::class)->sendPaymentReceipt($record);

                            Notification::make()
                                ->success()->seconds(4)
                                ->title('Receipt sent')
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->danger()->persistent()
                                ->title('Could not send receipt')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Phase 04 (docs/rebuild/specs/04-billing-and-receivables):
                // Accountant/Admin/Owner only — CompanyRole::paymentVerificationRoles().
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status->value === 'pending')
                    ->schema([
                        DatePicker::make('cheque_cleared_at')
                            ->label('Cheque cleared on')
                            ->visible(fn (Payment $record) => $record->method === PaymentMethod::Cheque->value)
                            ->helperText('A cheque stays pending until cleared — required before it can be verified.'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(VerifyCustomerPayment::class)->verify(
                                $record,
                                Auth::user(),
                                filled($data['cheque_cleared_at'] ?? null) ? Carbon::parse($data['cheque_cleared_at']) : null,
                            );

                            Notification::make()->success()->seconds(4)->title('Payment verified')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not verify payment')->body($e->getMessage())->send();
                        }
                    }),

                // "One payment allocates across multiple invoices/jobs only
                // within the same client/company" — FINALIZED-DECISIONS.md §4.
                Action::make('allocate')
                    ->label('Allocate')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->visible(fn (Payment $record) => ! $record->receipt()->exists())
                    ->schema(self::allocationSchema())
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(AllocateCustomerPayment::class)->allocate($record, self::mapAllocations($data['allocations']));

                            Notification::make()->success()->seconds(4)->title('Payment allocated')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not allocate payment')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('issueReceipt')
                    ->label('Issue receipt')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status->value === 'verified' && ! $record->receipt()->exists())
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        try {
                            $receipt = app(IssuePaymentReceipt::class)->issue($record);

                            Notification::make()->success()->seconds(4)->title('Receipt issued')->body("Receipt #{$receipt->number}.")->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not issue receipt')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('amendAllocation')
                    ->label('Amend allocation')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (Payment $record) => $record->receipt()->exists())
                    ->schema([
                        Textarea::make('reason')->required()->columnSpanFull(),
                        ...self::allocationSchema(),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(AmendPaymentAllocation::class)->amend(
                                $record,
                                self::mapAllocations($data['allocations']),
                                $data['reason'],
                                Auth::user(),
                            );

                            Notification::make()->success()->seconds(4)->title('Allocation amended')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not amend allocation')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('reverse')
                    ->label('Reverse')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Payment $record) => in_array($record->status->value, ['pending', 'verified'], true))
                    ->schema([
                        Textarea::make('reason')->required()->columnSpanFull(),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(ReverseCustomerPayment::class)->reverse($record, $data['reason'], Auth::user());

                            Notification::make()->success()->seconds(4)->title('Payment reversed')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not reverse payment')->body($e->getMessage())->send();
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

    /** @return array<int, mixed> */
    private static function allocationSchema(): array
    {
        return [
            Repeater::make('allocations')
                ->label('Invoice allocations')
                ->schema([
                    Select::make('invoice_id')
                        ->label('Invoice')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, Payment $record) => Invoice::query()
                            ->where('company_id', $record->company_id)
                            ->where('client_id', $record->client_id)
                            ->where('number', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('number', 'id'))
                        ->getOptionLabelUsing(fn ($value) => Invoice::find($value)?->number),
                    TextInput::make('amount')
                        ->numeric()
                        ->required(),
                ])
                ->columns(2)
                ->minItems(1)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<int|string, float>
     */
    private static function mapAllocations(array $allocations): array
    {
        $result = [];

        foreach ($allocations as $allocation) {
            $result[$allocation['invoice_id']] = (float) $allocation['amount'];
        }

        return $result;
    }
}
