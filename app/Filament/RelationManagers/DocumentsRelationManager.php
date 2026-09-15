<?php

namespace App\Filament\RelationManagers;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Shared across every documentable resource (Invoices, Expenses) — see
 * docs/invoiceninja-v4-schema-reference.md §2.8. Attach this same class to
 * any resource whose model has a `documents(): MorphMany` relation.
 */
class DocumentsRelationManager extends RelationManager
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

    protected static string $relationship = 'documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('File')
                ->directory('documents')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
                TextColumn::make('filename'),
                TextColumn::make('size')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 1024, 1).' KB' : '-'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $path = $data['path'];

                        return $this->getRelationship()->create([
                            'path' => $path,
                            'filename' => basename($path),
                            'mime_type' => Storage::disk('local')->mimeType($path) ?: null,
                            'size' => Storage::disk('local')->size($path),
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->url(fn (Document $record) => route('documents.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
