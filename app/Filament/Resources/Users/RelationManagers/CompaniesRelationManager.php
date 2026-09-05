<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesRelationManager extends RelationManager
{
    protected static string $relationship = 'companies';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->options(['owner' => 'Owner', 'member' => 'Member'])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('pivot.role')->label('Role')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options(['owner' => 'Owner', 'member' => 'Member'])
                            ->default('member')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
