<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord;
use App\Filament\Pages\Settings\Concerns\RestrictsToSettingsRoles;
use App\Models\CompanySetting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * See docs/filament-admin-layout-design.md §3.3. Maps to the legacy
 * `account_email_settings` table's subject/body pairs and reminder1-4
 * config.
 */
class EditEmailSettings extends Page
{
    use InteractsWithSettingsRecord;
    use RestrictsToSettingsRoles;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email & Reminders';

    public function getTitle(): string
    {
        return 'Email & Reminders';
    }

    protected function resolveRecord(): Model
    {
        return CompanySetting::query()->firstOrCreate(['company_id' => Filament::getTenant()->getKey()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Templates')
                    ->columns(1)
                    ->schema([
                        TextInput::make('invoice_email_subject'),
                        TextInput::make('invoice_email_body'),
                        TextInput::make('quote_email_subject'),
                        TextInput::make('quote_email_body'),
                        TextInput::make('payment_email_subject'),
                        TextInput::make('payment_email_body'),
                    ]),
                Section::make('Reminders')
                    ->description('Sent automatically relative to an invoice\'s due date or send date.')
                    ->schema(array_map(
                        fn (int $n) => Grid::make(4)
                            ->schema([
                                Toggle::make("reminder{$n}_enabled")
                                    ->label("Reminder {$n}")
                                    ->live(),
                                TextInput::make("reminder{$n}_days")
                                    ->label('Days')
                                    ->numeric()
                                    ->visible(fn (Get $get) => $get("reminder{$n}_enabled")),
                                Select::make("reminder{$n}_direction")
                                    ->label('Direction')
                                    ->options(['before' => 'Before', 'after' => 'After'])
                                    ->default('after')
                                    ->visible(fn (Get $get) => $get("reminder{$n}_enabled")),
                                Select::make("reminder{$n}_field")
                                    ->label('Relative to')
                                    ->options(['due_date' => 'Due date', 'invoice_date' => 'Invoice date'])
                                    ->default('due_date')
                                    ->visible(fn (Get $get) => $get("reminder{$n}_enabled")),
                            ]),
                        range(1, 4)
                    )),
                Section::make('Late fees')
                    ->schema(array_map(
                        fn (int $n) => Grid::make(2)
                            ->schema([
                                TextInput::make("late_fee{$n}_amount")->label("Tier {$n} amount")->numeric(),
                                TextInput::make("late_fee{$n}_percent")->label("Tier {$n} percent")->numeric(),
                            ]),
                        range(1, 3)
                    )),
            ]);
    }
}
