<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord;
use App\Filament\Pages\Settings\Concerns\RestrictsToSettingsRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Logo/color branding already lives directly on Company (see
 * EditCompanyProfile's "Branding" section, the tenant-profile page) — this
 * page just exposes the same `logo_path`/`primary_color`/`secondary_color`
 * columns as a proper Settings screen too, same rationale as
 * EditNumberingSettings: something this important to find (the logo prints
 * on every invoice/credit PDF — see Company::getLogoDataUri()) shouldn't
 * only live behind the tenant-switcher's "Edit profile" menu. Both forms
 * write to the same Company row, so a change from either page shows up on
 * the other. See docs/filament-admin-layout-design.md §3.1.
 */
class EditBrandingSettings extends Page
{
    use InteractsWithSettingsRecord;
    use RestrictsToSettingsRoles;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Branding';

    public function getTitle(): string
    {
        return 'Branding';
    }

    protected function resolveRecord(): Model
    {
        return Filament::getTenant();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Logo & colors')
                    ->description('Printed on invoice/credit PDFs and used on the public homepage/portal for this entity.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('logos')
                            ->columnSpanFull(),
                        ColorPicker::make('primary_color'),
                        ColorPicker::make('secondary_color'),
                    ]),
                Section::make('Signatory & banking')
                    ->description('For a future signature block and payment instructions on invoice/quotation PDFs — not yet printed on generated documents.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('signatory_name'),
                        TextInput::make('signatory_title'),
                        FileUpload::make('signature_image_path')
                            ->label('Signature image')
                            ->image()
                            ->directory('signatures')
                            ->columnSpanFull(),
                        TextInput::make('bank_name'),
                        TextInput::make('bank_account_number'),
                        TextInput::make('bank_account_name')
                            ->helperText('Beneficiary name as registered with the bank.'),
                        Textarea::make('payment_instructions')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
