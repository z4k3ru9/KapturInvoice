<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'invoice');
        }

        return $data;
    }
}
