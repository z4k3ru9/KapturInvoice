<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    // company_id is set automatically from the active Filament tenant
    // (Project::company()) — no field needed here.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->required()->columnSpanFull(),
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable(),
                DatePicker::make('due_date'),
                TextInput::make('task_rate')->numeric(),
                TextInput::make('budgeted_hours')->numeric(),
                TextInput::make('legacy_project_id')
                    ->numeric()
                    ->helperText('Legacy InvoiceNinja project id, for import traceability.'),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
