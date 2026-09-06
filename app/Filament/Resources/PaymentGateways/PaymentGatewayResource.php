<?php

namespace App\Filament\Resources\PaymentGateways;

use App\Filament\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Filament\Resources\PaymentGateways\Pages\ViewPaymentGateway;
use App\Filament\Resources\PaymentGateways\Schemas\PaymentGatewayForm;
use App\Filament\Resources\PaymentGateways\Schemas\PaymentGatewayInfolist;
use App\Filament\Resources\PaymentGateways\Tables\PaymentGatewaysTable;
use App\Models\PaymentGateway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentGatewayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListPaymentGateways/this table and on ViewPaymentGateway's header
    // (see docs/filament-admin-layout-design.md §6); 'view' stays a page.
    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGateways::route('/'),
            'view' => ViewPaymentGateway::route('/{record}'),
        ];
    }
}
