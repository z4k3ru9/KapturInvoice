<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CompaniesRelationManager extends RelationManager
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

    protected static string $relationship = 'companies';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->options(['owner' => 'Owner', 'member' => 'Member'])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('pivot.role')->label('Role')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->visible(fn () => static::canManageMembership())
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options(['owner' => 'Owner', 'member' => 'Member'])
                            ->default('member')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => static::canManageMembership()),
                DetachAction::make()->visible(fn () => static::canManageMembership()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->visible(fn () => static::canManageMembership()),
                ]),
            ]);
    }

    /**
     * Company-membership changes (attach/edit-role/detach) are Owner/Admin
     * only — "Owner/Admin invite internal users"
     * (docs/rebuild/specs/FINALIZED-DECISIONS.md §12) — reusing
     * CompanyPolicy::manageMembership the same way App\Policies\UserPolicy
     * does for the parent Users resource. Previously these actions had no
     * authorization check at all: any company member, Auditor included,
     * could attach/detach/re-role a company's users from here.
     */
    private static function canManageMembership(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        return $user->can('manageMembership', $tenant);
    }
}
