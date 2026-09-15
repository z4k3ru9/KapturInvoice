<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Actions\Portal\RevokePortalLink;
use App\Models\PortalLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Read-only list of the client's broader "portal links" (§5 of
 * FINALIZED-DECISIONS.md, generated from a contact's "Generate portal
 * link" action on ContactsRelationManager) — every row is written only by
 * `App\Actions\Portal\GeneratePortalLink`/`RevokePortalLink`, never a
 * generic create/edit here.
 */
class PortalLinksRelationManager extends RelationManager
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

    protected static string $relationship = 'portalLinks';

    protected static ?string $title = 'Portal Links';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('contact.name')->label('Contact'),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never'),
                TextColumn::make('revoked_at')->dateTime()->placeholder('-'),
                TextColumn::make('last_viewed_at')->dateTime()->placeholder('Never viewed'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PortalLink $record) => $record->revoked_at === null)
                    ->action(function (PortalLink $record) {
                        try {
                            app(RevokePortalLink::class)->revoke($record, Auth::user());

                            Notification::make()->success()->seconds(4)->title('Portal link revoked')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not revoke portal link')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
