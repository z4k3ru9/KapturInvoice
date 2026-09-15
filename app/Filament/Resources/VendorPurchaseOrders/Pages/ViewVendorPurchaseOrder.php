<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Pages;

use App\Filament\Resources\VendorPurchaseOrders\VendorPurchaseOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorPurchaseOrder extends ViewRecord
{
    protected static string $resource = VendorPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
