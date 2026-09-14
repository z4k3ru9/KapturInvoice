<?php

namespace App\Filament\Resources\VendorBills\RelationManagers;

use App\Actions\Procurement\AmendVendorPayment;
use App\Actions\Procurement\IssueVendorPaymentReceipt;
use App\Actions\Procurement\ReverseVendorPayment;
use App\Actions\Procurement\VerifyVendorPayment;
use App\Enums\VendorPaymentStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\VendorPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Read-only — "Record payment" already lives on the parent
 * VendorBillsTable's row action; this relation manager lists the
 * resulting App\Models\VendorPayment rows and carries their
 * verify/issue-receipt/reverse/amend lifecycle actions (the same
 * isReadOnly()-plus-custom-Action::make() pattern as
 * App\Filament\Resources\Invoices\Tables\InvoicesTable and
 * App\Filament\Resources\Payments\Tables\PaymentsTable), per
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')->placeholder('—'),
                TextColumn::make('amount')->numeric(),
                TextColumn::make('payment_date')->date(),
                TextColumn::make('status')->badge(),
                TextColumn::make('method')->placeholder('-'),
                TextColumn::make('reference')->placeholder('-'),
                TextColumn::make('receipt.number')->label('Receipt #')->placeholder('—'),
            ])
            ->recordActions([
                DownloadPdfAction::vendorPaymentReceipt()
                    ->visible(fn (VendorPayment $record) => $record->receipt()->exists())
                    ->url(fn (VendorPayment $record) => route('vendor-payment-receipts.pdf', $record->receipt)),
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (VendorPayment $record) => $record->status === VendorPaymentStatus::Pending)
                    ->schema([
                        DateTimePicker::make('cheque_cleared_at')
                            ->label('Cheque cleared at')
                            ->helperText('Only required for a cheque payment not already marked cleared.'),
                    ])
                    ->action(function (VendorPayment $record, array $data) {
                        try {
                            app(VerifyVendorPayment::class)->verify(
                                $record,
                                Auth::user(),
                                $data['cheque_cleared_at'] ?? null,
                            );

                            Notification::make()->success()->title('Vendor payment verified')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not verify vendor payment')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('issueReceipt')
                    ->label('Issue receipt')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (VendorPayment $record) => $record->status === VendorPaymentStatus::Verified && ! $record->receipt()->exists())
                    ->requiresConfirmation()
                    ->action(function (VendorPayment $record) {
                        try {
                            app(IssueVendorPaymentReceipt::class)->issue($record);

                            Notification::make()->success()->title('Vendor payment receipt issued')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not issue receipt')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('amend')
                    ->label('Amend')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (VendorPayment $record) => $record->receipt()->exists() && $record->status !== VendorPaymentStatus::Reversed)
                    ->schema([
                        TextInput::make('amount')->numeric()->required()->default(fn (VendorPayment $record) => $record->amount),
                        Textarea::make('reason')->required()->columnSpanFull(),
                    ])
                    ->action(function (VendorPayment $record, array $data) {
                        try {
                            app(AmendVendorPayment::class)->amend($record, (float) $data['amount'], $data['reason'], Auth::user());

                            Notification::make()->success()->title('Vendor payment amended')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not amend vendor payment')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('reverse')
                    ->label('Reverse')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (VendorPayment $record) => in_array($record->status, [VendorPaymentStatus::Pending, VendorPaymentStatus::Verified], true))
                    ->schema([
                        Textarea::make('reason')->required()->columnSpanFull(),
                    ])
                    ->action(function (VendorPayment $record, array $data) {
                        try {
                            app(ReverseVendorPayment::class)->reverse($record, $data['reason'], Auth::user());

                            Notification::make()->success()->title('Vendor payment reversed')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not reverse vendor payment')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
