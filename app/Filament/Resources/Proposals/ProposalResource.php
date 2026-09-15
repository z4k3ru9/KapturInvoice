<?php

namespace App\Filament\Resources\Proposals;

use App\Filament\Resources\Proposals\Pages\ListProposals;
use App\Filament\Resources\Proposals\Pages\ViewProposal;
use App\Filament\Resources\Proposals\Schemas\ProposalForm;
use App\Filament\Resources\Proposals\Schemas\ProposalInfolist;
use App\Filament\Resources\Proposals\Tables\ProposalsTable;
use App\Models\Proposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A full HTML/CSS document (quote cover letter/SOW) — see
 * docs/filament-admin-layout-design.md §2.7. Kept in its own nav group
 * rather than bolted onto Invoices/Quotes.
 */
class ProposalResource extends Resource
{
    protected static ?string $model = Proposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Proposals';

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * A separate formal proposal builder is deferred launch scope
     * (docs/REFACTOR_PLAN.md §1.1, Specs.md §3) — kept intact for any
     * already-created proposal, just out of the launch sidebar.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProposalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProposalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProposalsTable::configure($table);
    }

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListProposals/this table and on ViewProposal's header (see
    // docs/filament-admin-layout-design.md §6); 'view' stays a page.
    public static function getPages(): array
    {
        return [
            'index' => ListProposals::route('/'),
            'view' => ViewProposal::route('/{record}'),
        ];
    }
}
