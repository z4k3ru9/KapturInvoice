<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Actions\Billing\FileOrAdjustTaxRecap;
use App\Models\Invoice;
use App\Models\TaxRecap;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')
                    ->label('Client'),
                TextEntry::make('legacy_invoice_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('po_number')
                    ->placeholder('-'),
                TextEntry::make('invoice_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('due_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('currency_code')
                    ->placeholder('-'),
                TextEntry::make('exchange_rate')
                    ->numeric(),
                TextEntry::make('discount')
                    ->numeric(),
                IconEntry::make('discount_is_percentage')
                    ->boolean(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('tax_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('amount_paid')
                    ->numeric(),
                TextEntry::make('balance')
                    ->numeric(),
                TextEntry::make('partial_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('partial_due_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('terms')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('public_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('private_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('footer')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_recurring')
                    ->boolean(),
                TextEntry::make('recurring_frequency')
                    ->placeholder('-'),
                TextEntry::make('recurring_start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('recurring_end_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('recurring_last_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('recurringTemplate.number')
                    ->label('Recurring template')
                    ->placeholder('-'),
                IconEntry::make('auto_bill')
                    ->boolean(),
                TextEntry::make('convertedFromQuote.number')
                    ->label('Converted from quote')
                    ->placeholder('-'),
                TextEntry::make('sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('viewed_at')
                    ->dateTime()
                    ->placeholder('-'),

                Section::make('Billing (Phase 04)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pricing_mode')
                            ->badge(),
                        TextEntry::make('salesOrder.number')
                            ->label('Job')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('issued_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('voided_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('originalInvoice.number')
                            ->label('Corrects invoice')
                            ->placeholder('-'),
                        TextEntry::make('correction.number')
                            ->label('Corrected by')
                            ->placeholder('-'),
                        TextEntry::make('void_reason')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('correction_reason')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Tax snapshot')
                    ->visible(fn (Invoice $record) => $record->taxSnapshot !== null)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('taxSnapshot.taxable_base_total')->label('Taxable base')->numeric(),
                        TextEntry::make('taxSnapshot.tax_total')->label('Tax total')->numeric(),
                        TextEntry::make('taxSnapshot.rounding_adjustment')->label('Rounding adjustment')->numeric(),
                        TextEntry::make('taxSnapshot.captured_at')->label('Captured at')->dateTime(),
                    ]),

                Section::make('Tax recap')
                    ->visible(fn (Invoice $record) => $record->taxRecap !== null)
                    ->headerActions([
                        // Deliberately not DownloadPdfAction::taxRecap()
                        // + ->record() here — see that method's own
                        // docblock for the real infinite-recursion bug
                        // that combination causes on an Infolist Section
                        // header action. Deriving the TaxRecap through
                        // this closure instead, leaving the action's own
                        // record as the Invoice (matching the Section's
                        // own record), avoids it entirely.
                        Action::make('downloadTaxRecapPdf')
                            ->label('Download PDF')
                            ->icon(Heroicon::OutlinedDocumentArrowDown)
                            ->url(fn (Invoice $record) => route('tax-recaps.pdf', $record->taxRecap))
                            ->openUrlInNewTab(),
                        static::fileOrAdjustTaxRecapAction(),
                    ])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('taxRecap.number')->label('Number')->placeholder('-'),
                        TextEntry::make('taxRecap.reporting_period')->label('Reporting period'),
                        TextEntry::make('taxRecap.manual_entry_status')->label('Status')->badge(),
                        TextEntry::make('taxRecap.external_reference')->label('External reference')->placeholder('-'),
                        TextEntry::make('taxRecap.filing_date')->label('Filing date')->date()->placeholder('-'),
                    ]),

                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Invoice $record): bool => $record->trashed()),
            ]);
    }

    /**
     * "Recaps contain configurable fields for reporting period, external
     * tax-system reference/serial, manual-entry status, filing/entry
     * date, notes, and attachment/reference; adjustments require reason
     * and audit data." — FINALIZED-DECISIONS.md §33. See
     * App\Actions\Billing\FileOrAdjustTaxRecap's own docblock for why
     * this exists (a Codex review finding on PR #4).
     */
    private static function fileOrAdjustTaxRecapAction(): Action
    {
        return Action::make('fileOrAdjustTaxRecap')
            ->label(fn (Invoice $record) => $record->taxRecap?->filing_date || $record->taxRecap?->manual_entry_status === 'filed'
                ? 'Adjust filing'
                : 'File')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->schema(fn (Invoice $record) => [
                TextInput::make('external_reference')
                    ->label('External tax-system reference')
                    ->default(fn () => $record->taxRecap?->external_reference),
                Select::make('manual_entry_status')
                    ->label('Status')
                    ->options(['pending' => 'Pending', 'filed' => 'Filed'])
                    ->default(fn () => $record->taxRecap?->manual_entry_status ?? 'pending')
                    ->required(),
                DatePicker::make('filing_date')
                    ->default(fn () => $record->taxRecap?->filing_date),
                TextInput::make('attachment_reference')
                    ->label('Attachment reference')
                    ->default(fn () => $record->taxRecap?->attachment_reference),
                Textarea::make('notes')
                    ->default(fn () => $record->taxRecap?->notes)
                    ->columnSpanFull(),
                Textarea::make('adjustment_reason')
                    ->label('Reason for this adjustment')
                    ->visible(fn () => filled($record->taxRecap?->filing_date) || $record->taxRecap?->manual_entry_status === 'filed')
                    ->columnSpanFull(),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    app(FileOrAdjustTaxRecap::class)->file(
                        $record->taxRecap,
                        $data,
                        Auth::user(),
                        $data['adjustment_reason'] ?? null,
                    );

                    Notification::make()->success()->seconds(4)->title('Tax recap updated')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->persistent()->title('Could not update tax recap')->body($e->getMessage())->send();
                }
            });
    }
}
