<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cross-cutting browse/search view over every document, wherever it's
 * attached (Invoice or Expense) — see
 * docs/filament-admin-layout-design.md §2.6. Uploads happen on the parent's
 * own DocumentsRelationManager; this resource has no create form.
 */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    protected static string|UnitEnum|null $navigationGroup = 'Documents';

    protected static ?string $recordTitleAttribute = 'filename';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')->searchable(),
                TextColumn::make('documentable_type')
                    ->label('Attached to')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('size')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 1024, 1).' KB' : '-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->url(fn (Document $record) => route('documents.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
        ];
    }
}
