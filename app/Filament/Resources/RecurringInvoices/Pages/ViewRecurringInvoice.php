<?php

namespace App\Filament\Resources\RecurringInvoices\Pages;

use App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecurringInvoice extends ViewRecord
{
    protected static string $resource = RecurringInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
