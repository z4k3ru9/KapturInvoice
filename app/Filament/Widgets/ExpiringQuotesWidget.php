<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Upcoming & Expired Quotes" — see docs/filament-admin-layout-design.md
 * §9. Quotes whose `due_date` (this rebuild's stand-in for a quote's
 * "valid until" — there's no separate expiry column) falls within the
 * next 14 days or has already passed, excluding ones already converted
 * to an invoice (`Invoice::convertedInvoices()`). Deliberately not
 * period-filtered — like Pending/Overdue invoices on RevenueOverview,
 * this is a live "needs attention" list, not a historical report.
 */
class ExpiringQuotesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    // See RevenueOverview's $isLazy for why.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Upcoming & expired quotes')
            ->query(
                Invoice::query()
                    ->where('type', InvoiceType::Quote)
                    ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed])
                    ->whereDoesntHave('convertedInvoices')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', now()->addDays(14))
                    ->orderBy('due_date')
            )
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('due_date')
                    ->label('Valid until')
                    ->date()
                    ->color(fn (Invoice $record) => $record->due_date?->isPast() ? 'danger' : 'warning'),
                TextColumn::make('total')->numeric(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(fn (Invoice $record) => QuoteResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
