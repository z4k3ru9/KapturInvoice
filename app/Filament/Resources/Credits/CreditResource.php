<?php

namespace App\Filament\Resources\Credits;

use App\Filament\Resources\Credits\Pages\ListCredits;
use App\Filament\Resources\Credits\Pages\ViewCredit;
use App\Filament\Resources\Credits\Schemas\CreditForm;
use App\Filament\Resources\Credits\Schemas\CreditInfolist;
use App\Filament\Resources\Credits\Tables\CreditsTable;
use App\Models\Credit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CreditResource extends Resource
{
    protected static ?string $model = Credit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return CreditForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CreditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListCredits/this table and on ViewCredit's header (see
    // docs/filament-admin-layout-design.md §6); 'view' stays a page. The
    // Create modal's number-assignment lives on ListCredits' CreateAction
    // now (mutateDataUsing()), replacing the old CreateCredit page hook.
    public static function getPages(): array
    {
        return [
            'index' => ListCredits::route('/'),
            'view' => ViewCredit::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
