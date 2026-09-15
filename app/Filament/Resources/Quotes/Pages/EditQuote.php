<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            // docs/REFACTOR_PLAN.md drift audit: guards the single-record
            // action the same way QuotesTable::forceDeleteBulkAction()
            // guards the bulk one.
            ForceDeleteAction::make()
                ->before(function (Invoice $record) {
                    if (! QuotesTable::isSafeToForceDelete($record)) {
                        Notification::make()
                            ->danger()->persistent()
                            ->title('Could not force-delete quote')
                            ->body('Only a Draft quote with no payments, allocations, or converted invoice can be permanently deleted.')
                            ->send();

                        throw new Halt;
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
