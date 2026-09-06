<?php

namespace App\Filament\Resources\PaymentGateways\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentGatewayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('driver')->badge(),
                IconEntry::make('is_enabled')->boolean(),
                TextEntry::make('fee_amount')->numeric(),
                TextEntry::make('fee_percent')->numeric()->suffix('%'),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('updated_at')->dateTime()->placeholder('-'),
            ]);
    }
}
