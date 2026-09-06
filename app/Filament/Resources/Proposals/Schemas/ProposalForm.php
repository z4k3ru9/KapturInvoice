<?php

namespace App\Filament\Resources\Proposals\Schemas;

use App\Enums\ProposalStatus;
use App\Models\ProposalTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProposalForm
{
    // company_id is set automatically from the active Filament tenant
    // (Proposal::company()) — client/template relationship() selects are
    // tenant-scoped via App\Models\Concerns\BelongsToCompany.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposal')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable(),
                        Select::make('status')
                            ->options(ProposalStatus::class)
                            ->default(ProposalStatus::Draft)
                            ->required(),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->default(0),
                        DatePicker::make('valid_until'),
                        Select::make('proposal_template_id')
                            ->label('Start from template')
                            ->helperText('Copies the template\'s content below — only applies once, when picked.')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($template = ProposalTemplate::find($state)) {
                                    $set('html', $template->html);
                                    $set('css', $template->css);
                                }
                            }),
                        TextInput::make('legacy_proposal_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja proposal id, for import traceability.'),
                    ]),
                Section::make('Content')
                    ->schema([
                        RichEditor::make('html')->columnSpanFull(),
                        Textarea::make('css')
                            ->rows(6)
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
