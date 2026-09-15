<?php

namespace App\Filament\Resources\PriceListItems\Tables;

use App\Models\PriceListItem;
use App\Services\ProductSync;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PriceListItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand')
                    ->badge()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU / Basic Model')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('reference_price')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('products_exists')
                    ->label('Linked product')
                    ->boolean()
                    ->state(fn (PriceListItem $record): bool => $record->products()->exists()),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source_file')
                    ->label('Source file')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sku')
            ->filters([
                SelectFilter::make('brand')
                    ->options(fn (): array => PriceListItem::query()
                        ->distinct()
                        ->orderBy('brand')
                        ->pluck('brand', 'brand')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Turns/refreshes this reference row into a real, invoiceable
                // Product (App\Services\ProductSync) — see the class
                // docblock on PriceListItem. Linked via
                // products.price_list_item_id, so re-running this after a
                // re-import refreshes the same Product rather than
                // duplicating it.
                Action::make('syncProduct')
                    ->label('Create/update product')
                    ->icon(Heroicon::OutlinedCube)
                    ->action(function (PriceListItem $record) {
                        $product = app(ProductSync::class)->createOrUpdateFromPriceListItem($record);

                        Notification::make()
                            ->success()->seconds(4)
                            ->title($product->wasRecentlyCreated ? 'Product created' : 'Product updated')
                            ->body("Product #{$product->id} ({$product->sku}) is now linked to this pricelist row.")
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
