<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    // The password's hash is applied by User's own `'password' => 'hashed'`
    // cast — no dehydrateStateUsing() here, just the plain value.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                Toggle::make('is_super_admin')
                    ->label('Super admin')
                    ->helperText('Super admins can access every company, bypassing membership checks.'),
            ]);
    }
}
