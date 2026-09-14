<?php

namespace App\Filament\Support;

use App\Models\Credit;
use App\Models\DeliveryOrder;
use App\Models\HandoverReport;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\TaxRecap;
use App\Models\VendorBill;
use App\Models\VendorPaymentReceipt;
use App\Models\VendorPurchaseOrder;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Shared "Download PDF" table action for Invoices/Quotes/Recurring
 * Invoices, Credits, Quotations, Proposals, Sales Orders, and Receipts
 * (§7) — a plain link to the (auth-guarded, outside the Filament panel)
 * PDF route, opened in a new tab, rather than a Filament action with its
 * own processing step.
 */
class DownloadPdfAction
{
    public static function invoice(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (Invoice $record) => route('invoices.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function credit(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (Credit $record) => route('credits.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function taxRecap(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (TaxRecap $record) => route('tax-recaps.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function quotation(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (Quotation $record) => route('quotations.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function proposal(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (Proposal $record) => route('proposals.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function salesOrder(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (SalesOrder $record) => route('sales-orders.pdf', $record))
            ->openUrlInNewTab();
    }

    /** Surfaced from a Payment row once it has an issued Receipt — see PaymentsTable. */
    public static function receipt(): Action
    {
        return Action::make('downloadReceiptPdf')
            ->label('Download receipt PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->visible(fn ($record) => $record->receipt()->exists())
            ->url(fn ($record) => route('receipts.pdf', $record->receipt))
            ->openUrlInNewTab();
    }

    public static function vendorPurchaseOrder(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (VendorPurchaseOrder $record) => route('vendor-purchase-orders.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function vendorBill(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (VendorBill $record) => route('vendor-bills.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function vendorPaymentReceipt(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (VendorPaymentReceipt $record) => route('vendor-payment-receipts.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function deliveryOrder(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (DeliveryOrder $record) => route('delivery-orders.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function handoverReport(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (HandoverReport $record) => route('handover-reports.pdf', $record))
            ->openUrlInNewTab();
    }
}
