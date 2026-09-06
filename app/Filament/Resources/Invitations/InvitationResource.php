<?php

namespace App\Filament\Resources\Invitations;

use App\Filament\Resources\Invitations\Pages\ListInvitations;
use App\Models\Invitation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The admin-side view of the client-portal magic-link flow — see
 * docs/invoiceninja-v4-schema-reference.md §2.3/§4 and
 * docs/filament-admin-layout-design.md §2.2. Read-mostly: invitations are
 * generated when an invoice is sent, not created here by hand. Invitation
 * has no company_id column (it's scoped via its invoice), so tenant
 * scoping is applied explicitly below rather than via BelongsToCompany.
 */
class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?string $navigationLabel = 'Client Portal Invitations';

    // Invitation has no company_id/company() relation of its own (it's
    // scoped via its invoice) — Filament's automatic tenant scoping
    // requires a direct ownership relationship, so it's disabled here in
    // favour of the explicit whereHas() scoping in getEloquentQuery().
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                Filament::hasTenancy() && ($tenant = Filament::getTenant()),
                fn (Builder $query, $tenant) => $query->whereHas(
                    'invoice',
                    fn (Builder $q) => $q->where('company_id', Filament::getTenant()->getKey())
                )
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.number')->label('Invoice')->searchable(),
                TextColumn::make('contact.name')->label('Contact'),
                TextColumn::make('sent_at')->dateTime()->placeholder('-'),
                TextColumn::make('viewed_at')->dateTime()->placeholder('-'),
                IconColumn::make('signed_at')
                    ->label('Signed')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->getStateUsing(fn (Invitation $record) => filled($record->signed_at)),
            ])
            ->recordActions([
                Action::make('copyLink')
                    ->label('Copy portal link')
                    ->icon(Heroicon::OutlinedClipboard)
                    ->action(fn () => null)
                    ->extraAttributes(fn (Invitation $record) => [
                        'x-on:click' => 'window.navigator.clipboard.writeText(\''.url('/portal/'.$record->key).'\')',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}
