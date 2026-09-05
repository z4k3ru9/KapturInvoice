<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\CompaniesRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * See docs/filament-admin-layout-design.md §2.8. A user belongs to
 * companies via the `company_user` pivot (role per membership), not a
 * single company_id — Filament's automatic tenant scoping needs a direct
 * ownership relationship, so it's disabled here (like InvitationResource)
 * in favour of the explicit whereHas() scoping below. RBAC beyond
 * super-admin/company-member is deferred to filament-shield.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CompaniesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                Filament::hasTenancy() && ($tenant = Filament::getTenant()),
                fn (Builder $query) => $query->whereHas(
                    'companies',
                    fn (Builder $q) => $q->whereKey(Filament::getTenant()->getKey())
                )
            );
    }
}
