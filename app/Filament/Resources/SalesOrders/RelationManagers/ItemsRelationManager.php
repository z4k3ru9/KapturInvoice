<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: a job's items are a snapshot copied once from the accepted
 * quotation (App\Actions\Sales\CreateSalesOrderFromQuotation) and never
 * edited directly — "Preserve accepted quotation values as the job
 * source snapshot" (Specs.md). A scope change goes through a job
 * variation instead (VariationsRelationManager), which changes the job's
 * billable value without rewriting this snapshot.
 */
class ItemsRelationManager extends RelationManager
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

    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('unit_cost')->numeric(),
                TextColumn::make('line_total')->numeric(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
