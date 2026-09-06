<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('legacy_project_id')->numeric()->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('client.name')->label('Client')->placeholder('-'),
                TextEntry::make('due_date')->date()->placeholder('-'),
                TextEntry::make('task_rate')->numeric()->placeholder('-'),
                TextEntry::make('budgeted_hours')->numeric()->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Project $record): bool => $record->trashed()),
            ]);
    }
}
