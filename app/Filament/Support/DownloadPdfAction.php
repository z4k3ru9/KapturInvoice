<?php

namespace App\Filament\Support;

use App\Models\Credit;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Shared "Download PDF" table action for Invoices/Quotes/Recurring
 * Invoices and Credits (§7) — a plain link to the (auth-guarded, outside
 * the Filament panel) PDF route, opened in a new tab, rather than a
 * Filament action with its own processing step.
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
}
