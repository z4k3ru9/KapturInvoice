<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tracks one active/last timer per task (started_at/stopped_at) rather than
 * a full multi-segment child table — see the tasks migration and
 * docs/filament-admin-layout-design.md §2.5.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('description')->columnSpanFull(),
                Select::make('task_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description'),
                TextColumn::make('status.name')->label('Status')->badge(),
                IconColumn::make('is_running')->boolean(),
                TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->state(fn (Task $record) => $record->duration_seconds !== null
                        ? gmdate('H:i:s', $record->duration_seconds)
                        : '-'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('startTimer')
                    ->label('Start')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (Task $record) => ! $record->is_running)
                    ->action(fn (Task $record) => $record->update([
                        'started_at' => now(),
                        'stopped_at' => null,
                        'is_running' => true,
                    ])),
                Action::make('stopTimer')
                    ->label('Stop')
                    ->icon(Heroicon::OutlinedStop)
                    ->visible(fn (Task $record) => $record->is_running)
                    ->action(fn (Task $record) => $record->update([
                        'stopped_at' => now(),
                        'is_running' => false,
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
