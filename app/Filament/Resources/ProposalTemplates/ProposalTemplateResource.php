<?php

namespace App\Filament\Resources\ProposalTemplates;

use App\Filament\Resources\ProposalTemplates\Pages\ListProposalTemplates;
use App\Models\ProposalTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Reusable starting points for Proposals — see
 * docs/filament-admin-layout-design.md §2.7. Picking one on the Proposal
 * form copies its html/css into the new proposal (see ProposalForm).
 */
class ProposalTemplateResource extends Resource
{
    protected static ?string $model = ProposalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Proposals';

    protected static ?string $recordTitleAttribute = 'name';

    // company_id is set automatically from the active Filament tenant
    // (ProposalTemplate::company()) — no field needed here.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->columnSpanFull(),
            RichEditor::make('html')->columnSpanFull(),
            Textarea::make('css')
                ->rows(6)
                ->extraInputAttributes(['class' => 'font-mono text-xs'])
                ->columnSpanFull(),
            TextInput::make('legacy_proposal_template_id')
                ->numeric()
                ->helperText('Legacy InvoiceNinja proposal_template id, for import traceability.'),
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
    // ListProposalTemplates/this table (see
    // docs/filament-admin-layout-design.md §6).
    public static function getPages(): array
    {
        return [
            'index' => ListProposalTemplates::route('/'),
        ];
    }
}
