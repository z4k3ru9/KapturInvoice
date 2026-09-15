<?php

namespace App\Filament\Widgets;

use App\Enums\QuotationStatus;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Expiring quotations" — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md D8.
 * Replaces the legacy ExpiringQuotesWidget (which read the frozen legacy
 * `invoices`/`type=quote` rows, whose expiry is not actionable): this one
 * reads the canonical `Quotation.valid_until` — a quotation only becomes
 * "expiring" once it's actually Sent to the customer, and only within a
 * week of its validity date or already past it. Deliberately not
 * period-filtered — like Outstanding/Overdue on RevenueOverview, this is a
 * live "needs attention" list, not a historical report.
 */
class ExpiringQuotationsWidget extends TableWidget
{
    protected int|string|array $columnSpan = ['lg' => 3];

    // See RevenueOverview's $isLazy for why.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Expiring quotations')
            ->query(
                Quotation::query()
                    ->where('status', QuotationStatus::Sent)
                    ->whereNotNull('valid_until')
                    ->where('valid_until', '<=', now()->addDays(7))
                    ->orderBy('valid_until')
            )
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('valid_until')
                    ->label('Valid until')
                    ->date()
                    ->color(fn (Quotation $record) => $record->valid_until?->isPast() ? 'danger' : 'warning'),
                TextColumn::make('total')->numeric(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(fn (Quotation $record) => QuotationResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all quotations')
                    ->url(fn () => QuotationResource::getUrl('index')),
            ])
            ->emptyStateHeading('No quotations expiring soon.')
            ->paginated(false);
    }
}
