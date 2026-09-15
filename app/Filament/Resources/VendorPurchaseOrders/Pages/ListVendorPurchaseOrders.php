<?php

namespace App\Filament\Resources\VendorPurchaseOrders\Pages;

use App\Filament\Resources\VendorPurchaseOrders\VendorPurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorPurchaseOrders extends ListRecords
{
    protected static string $resource = VendorPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
