<?php

namespace App\Filament\Resources\VendorBills\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — "Record payment" already lives on the parent
 * VendorBillsTable's row action, so this relation manager only lists the
 * resulting App\Models\VendorPayment rows.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('amount')->numeric(),
                TextColumn::make('payment_date')->date(),
                TextColumn::make('method')->placeholder('-'),
                TextColumn::make('reference')->placeholder('-'),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
