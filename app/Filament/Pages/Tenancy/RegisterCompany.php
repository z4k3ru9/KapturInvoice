<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register company';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Company::class, 'slug'),
                TextInput::make('domain')
                    ->helperText('Public homepage domain for this entity, e.g. example.com')
                    ->maxLength(255)
                    ->unique(Company::class, 'domain'),
                TextInput::make('currency_code')
                    ->label('Default currency')
                    ->default('USD')
                    ->length(3)
                    ->required(),
                ColorPicker::make('primary_color'),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $company = Company::create($data);

        $company->users()->attach(Filament::auth()->id(), ['role' => 'owner']);

        return $company;
    }
}
