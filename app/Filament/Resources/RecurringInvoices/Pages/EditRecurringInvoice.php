<?php

namespace App\Filament\Resources\RecurringInvoices\Pages;

use App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource;
use App\Filament\Resources\RecurringInvoices\Tables\RecurringInvoicesTable;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditRecurringInvoice extends EditRecord
{
    protected static string $resource = RecurringInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            // docs/REFACTOR_PLAN.md drift audit: guards the single-record
            // action the same way RecurringInvoicesTable::forceDeleteBulkAction()
            // guards the bulk one.
            ForceDeleteAction::make()
                ->before(function (Invoice $record) {
                    if (! RecurringInvoicesTable::isSafeToForceDelete($record)) {
                        Notification::make()
                            ->danger()->persistent()
                            ->title('Could not force-delete recurring invoice')
                            ->body('Only a recurring template with no generated invoices can be permanently deleted.')
                            ->send();

                        throw new Halt;
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
