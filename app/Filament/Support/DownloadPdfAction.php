<?php

namespace App\Filament\Support;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Shared "Download PDF" table action for Invoices/Quotes/Recurring
 * Invoices/Credits, and the Phase 03 Quotation/Proposal resources — a
 * plain link to the (auth-guarded, outside the Filament panel) PDF route,
 * opened in a new tab, rather than a Filament action with its own
 * processing step.
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
}
