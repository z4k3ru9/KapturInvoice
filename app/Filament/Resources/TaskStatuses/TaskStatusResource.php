<?php

namespace App\Filament\Resources\TaskStatuses;

use App\Filament\Resources\TaskStatuses\Pages\CreateTaskStatus;
use App\Filament\Resources\TaskStatuses\Pages\EditTaskStatus;
use App\Filament\Resources\TaskStatuses\Pages\ListTaskStatuses;
use App\Models\TaskStatus;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TaskStatusResource extends Resource
{
    protected static ?string $model = TaskStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $recordTitleAttribute = 'name';

    // company_id is set automatically from the active Filament tenant
    // (TaskStatus::company()) — no field needed here.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('sort_order')->numeric()->default(0),
            TextInput::make('legacy_task_status_id')
                ->numeric()
                ->helperText('Legacy InvoiceNinja task_status id, for import traceability.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskStatuses::route('/'),
            'create' => CreateTaskStatus::route('/create'),
            'edit' => EditTaskStatus::route('/{record}/edit'),
        ];
    }
}
