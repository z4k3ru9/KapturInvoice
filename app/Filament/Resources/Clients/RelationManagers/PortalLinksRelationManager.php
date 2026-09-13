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

                            Notification::make()->success()->title('Portal link revoked')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not revoke portal link')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
