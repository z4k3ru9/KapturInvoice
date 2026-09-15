<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\CatalogItemType;
use App\Models\Product;
use App\Services\ProposalSnippetSync;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // No label — an image reads as identity, not data (DESIGN §15).
                ImageColumn::make('image_path')
                    ->label('')
                    ->circular(false)
                    ->imageSize(40)
                    ->extraImgAttributes(['class' => 'rounded-lg border border-gray-200 bg-white'])
                    ->visibleFrom('md'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Product $record): ?string => Str::limit((string) $record->description, 90) ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Default price')
                    ->money(fn () => Filament::getTenant()->currency_code)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('tax_category')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('stock_flag')
                    ->label('Normally stocked')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('defaultTaxRate.name')
                    ->label('Default tax rate')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(CatalogItemType::class),
                // Label stays "Normally stocked" — stock_flag is a display
                // label only, never real inventory (Specs 02).
                TernaryFilter::make('stock_flag')
                    ->label('Normally stocked')
                    ->placeholder('All')
                    ->trueLabel('Stocked')
                    ->falseLabel('Non-stocked / services'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->extraModalFooterActions([
                        DeleteAction::make()->requiresConfirmation(),
                    ]),
                // Only meaningful once the product has a picture — see
                // App\Services\ProposalSnippetSync's docblock for why this
                // is the only path a product picture reaches a Proposal.
                Action::make('createProposalSnippet')
                    ->label('Create proposal snippet')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (Product $record) => filled($record->image_path))
                    ->action(function (Product $record) {
                        $snippet = app(ProposalSnippetSync::class)->createOrUpdateFromProduct($record);

                        Notification::make()
                            ->success()
                            ->title($snippet->wasRecentlyCreated ? 'Proposal snippet created' : 'Proposal snippet updated')
                            ->body("Copy \"{$snippet->name}\" from Proposal Snippets into a proposal's content.")
                            ->send();
                    }),
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
