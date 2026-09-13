<?php

namespace App\Filament\Resources\VendorBills\Pages;

use App\Filament\Resources\VendorBills\VendorBillResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorBill extends CreateRecord
{
    protected static string $resource = VendorBillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'vendor_bill');
        }

        return $data;
    }
}
