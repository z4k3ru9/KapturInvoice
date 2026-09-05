<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord;
use App\Models\CompanySetting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * See docs/filament-admin-layout-design.md §3.5. Governs the public-facing
 * invitation/magic-link flow (App\Models\Invitation) — this admin page only
 * toggles behaviour, it never renders the portal itself.
 */
class EditClientPortalSettings extends Page
{
    use InteractsWithSettingsRecord;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Client Portal';

    public function getTitle(): string
    {
        return 'Client Portal';
    }

    protected function resolveRecord(): Model
    {
        return CompanySetting::query()->firstOrCreate(['company_id' => Filament::getTenant()->getKey()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client portal')
                    ->schema([
                        Toggle::make('portal_enabled')
                            ->label('Enable client portal')
                            ->helperText('When off, invitation links still exist but resolve to a disabled-portal message.'),
                        Toggle::make('portal_allow_client_payments')
                            ->label('Allow clients to pay from the portal'),
                        Toggle::make('portal_show_tasks')
                            ->label('Show tasks in the portal'),
                        Toggle::make('portal_require_signature')
                            ->label('Require signature on invoice approval'),
                    ]),
            ]);
    }
}
