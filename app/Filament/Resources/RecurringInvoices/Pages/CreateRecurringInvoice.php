<?php

namespace App\Filament\Resources\RecurringInvoices\Pages;

use App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringInvoice extends CreateRecord
{
    protected static string $resource = RecurringInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_recurring'] = true;

        // The template itself gets a number from the invoice sequence too
        // (matching InvoiceForm's helper text); each generated instance
        // gets its own via InvoiceDuplicator::generateRecurringInstance().
        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'invoice');
        }

        return $data;
    }
}
