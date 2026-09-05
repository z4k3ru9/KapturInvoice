<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Numbering sequences already live directly on Company (see the
 * companies migration); this page just exposes them as a proper Settings
 * screen instead of only via the tenant-profile page — plus the
 * account-level default terms/taxes. See
 * docs/filament-admin-layout-design.md §3.2.
 */
class EditNumberingSettings extends Page
{
    use InteractsWithSettingsRecord;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Invoice & Numbering';

    public function getTitle(): string
    {
        return 'Invoice & Numbering';
    }

    protected function resolveRecord(): Model
    {
        return Filament::getTenant();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Numbering sequences')
                    ->description('Each sequence auto-increments independently per company.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_prefix')->maxLength(20),
                        TextInput::make('invoice_next_number')->numeric()->required(),
                        TextInput::make('quote_prefix')->maxLength(20),
                        TextInput::make('quote_next_number')->numeric()->required(),
                        TextInput::make('credit_prefix')->maxLength(20),
                        TextInput::make('credit_next_number')->numeric()->required(),
                    ]),
                Section::make('Defaults')
                    ->description('Applied to new invoices/quotes unless overridden on the document itself.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('default_payment_terms')->columnSpanFull(),
                        Select::make('default_tax_rate_1_id')
                            ->label('Default tax 1')
                            ->options(fn () => TaxRate::query()->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('default_tax_rate_2_id')
                            ->label('Default tax 2')
                            ->options(fn () => TaxRate::query()->pluck('name', 'id'))
                            ->searchable(),
                    ]),
            ]);
    }
}
