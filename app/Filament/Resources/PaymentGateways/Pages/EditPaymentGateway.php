<?php

namespace App\Filament\Resources\PaymentGateways\Pages;

use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentGateway extends EditRecord
{
    protected static string $resource = PaymentGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
