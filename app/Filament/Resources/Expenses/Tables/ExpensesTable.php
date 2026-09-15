<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Models\Expense;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.name')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('expense_date')->date()->sortable(),
                TextColumn::make('subtotal')->label('Amount')->money(fn (Expense $record) => $record->currency_code)->sortable(),
                TextColumn::make('total')->money(fn (Expense $record) => $record->currency_code)->sortable(),
                IconColumn::make('should_be_invoiced')->label('Rebill')->boolean(),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('documents_count')->counts('documents')->label('Documents'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable(),
                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable(),
                TernaryFilter::make('should_be_invoiced')
                    ->label('Rebill')
                    ->placeholder('All expenses')
                    ->trueLabel('Marked for rebill')
                    ->falseLabel('Not rebilled'),
                Filter::make('expense_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
