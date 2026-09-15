<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Pages;

use App\Filament\Resources\VendorPurchaseOrders\VendorPurchaseOrderResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorPurchaseOrder extends CreateRecord
{
    protected static string $resource = VendorPurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'vendor_purchase_order');
        }

        return $data;
    }
}
