<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Company profile';
    }

    /** "View settings: Owner/Admin ... Auditor never" (docs/rebuild/Specs.md §10). */
    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        return $user->can('viewSettings', $tenant);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Company::class, 'slug', ignoreRecord: true),
                        TextInput::make('domain')
                            ->helperText('Public homepage domain for this entity.')
                            ->maxLength(255)
                            ->unique(Company::class, 'domain', ignoreRecord: true),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(255),
                        TextInput::make('tax_number')
                            ->label('Tax ID')
                            ->helperText('Printed on invoice/credit PDFs.')
                            ->maxLength(255),
                        TextInput::make('currency_code')->label('Default currency')->length(3),
                    ]),
                Section::make('Branding')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo_path')->image()->directory('logos'),
                        ColorPicker::make('primary_color'),
                        ColorPicker::make('secondary_color'),
                    ]),
                Section::make('Document numbering')
                    ->columns(3)
                    ->schema([
                        TextInput::make('code')
                            ->label('Company code')
                            ->helperText(fn (?Company $record) => filled($record?->codes_locked_at)
                                ? 'Locked: this company has already issued a numbered document.'
                                : 'Used in new document numbers, e.g. KJA-INV-2026090001. Locks after the first document is issued.')
                            ->maxLength(20)
                            ->disabled(fn (?Company $record) => filled($record?->codes_locked_at))
                            ->dehydrated(),
                        TextInput::make('invoice_prefix')->maxLength(20),
                        TextInput::make('quote_prefix')->maxLength(20),
                        TextInput::make('credit_prefix')->maxLength(20),
                    ]),
            ]);
    }
}
