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
