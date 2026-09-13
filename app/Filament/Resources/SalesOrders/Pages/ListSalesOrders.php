<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction here — a job is only ever created via the "Create job"
 * action on an accepted Quotation (App\Actions\Sales\CreateSalesOrderFromQuotation),
 * never by filling in a blank job form.
 */
class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;
}
