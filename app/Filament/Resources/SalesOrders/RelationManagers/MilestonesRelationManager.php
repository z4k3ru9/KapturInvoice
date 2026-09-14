<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\MilestoneType;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Support direct full payment or custom milestones by amount,
 * percentage, description, and due date" (Specs.md). Milestones are only
 * editable while the job is still Draft — once Approved,
 * App\Actions\Sales\ApproveSalesOrder has already validated their total
 * against the job's approved value, and Specs.md's "Draft milestones may
 * differ temporarily" implies they stop being provisional at that point.
 */
class MilestonesRelationManager extends RelationManager
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

    protected static string $relationship = 'milestones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->options(MilestoneType::class)
                    ->default(MilestoneType::Custom)
                    ->required()
                    ->live(),
                TextInput::make('description')->columnSpanFull(),
                TextInput::make('amount')->numeric()->required()->default(0),
                Toggle::make('is_percentage')->label('Amount is a percentage of job value')->live(),
                TextInput::make('percentage')
                    ->numeric()
                    ->visible(fn (Get $get) => (bool) $get('is_percentage')),
                DatePicker::make('due_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('description')->placeholder('-'),
                TextColumn::make('amount')->numeric(),
                IconColumn::make('is_percentage')->boolean(),
                TextColumn::make('due_date')->date()->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn () => $this->isEditable()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => $this->isEditable()),
                DeleteAction::make()->visible(fn () => $this->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $this->isEditable()),
                ]),
            ]);
    }

    private function isEditable(): bool
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = $this->getOwnerRecord();

        return $salesOrder->status === SalesOrderStatus::Draft;
    }
}
