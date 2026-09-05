<?php

namespace App\Filament\Resources\ProposalSnippets;

use App\Filament\Resources\ProposalSnippets\Pages\ListProposalSnippets;
use App\Models\ProposalSnippet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Reusable HTML fragments (a standard terms block, a team-bio blurb, …)
 * droppable into a Proposal's body — see
 * docs/filament-admin-layout-design.md §2.7.
 */
class ProposalSnippetResource extends Resource
{
    protected static ?string $model = ProposalSnippet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Proposals';

    protected static ?string $recordTitleAttribute = 'name';

    // company_id is set automatically from the active Filament tenant
    // (ProposalSnippet::company()) — no field needed here.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->columnSpanFull(),
            RichEditor::make('html')->columnSpanFull(),
            TextInput::make('legacy_proposal_snippet_id')
                ->numeric()
                ->helperText('Legacy InvoiceNinja proposal_snippet id, for import traceability.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListProposalSnippets/this table (see
    // docs/filament-admin-layout-design.md §6).
    public static function getPages(): array
    {
        return [
            'index' => ListProposalSnippets::route('/'),
        ];
    }
}
