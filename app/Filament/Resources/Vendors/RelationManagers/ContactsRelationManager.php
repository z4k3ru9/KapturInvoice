<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    /**
     * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
     * a real bug found via browser testing: Filament's relation-manager
     * lazy loading (Filament\Support\Concerns\CanBeLazy, `$isLazy = true`
     * by default) never actually initializes on a genuine full page load
     * (only on Livewire's own `wire:navigate` soft navigation) — the tab
     * gets stuck showing its "Loading..." placeholder forever, with no
     * Livewire request ever firing to mount it. Same root cause already
     * documented for the three dashboard widgets in CLAUDE.md/
     * docs/filament-admin-layout-design.md §9 (a Filament/Livewire lazy-
     * loading bug, not this app's) — same fix: turn lazy loading off.
     */
    protected static bool $isLazy = false;

    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('first_name')->maxLength(255),
                TextInput::make('last_name')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
                Toggle::make('is_primary')
                    ->label('Primary contact')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                TextColumn::make('first_name'),
                TextColumn::make('last_name'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                IconColumn::make('is_primary')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
