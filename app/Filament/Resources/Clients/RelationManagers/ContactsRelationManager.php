<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Actions\Portal\GeneratePortalLink;
use App\Models\Contact;
use App\Services\BillingMailer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

class ContactsRelationManager extends RelationManager
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

    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('first_name')->maxLength(255),
                TextInput::make('last_name')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
                Toggle::make('is_primary')
                    ->label('Primary contact'),
                Toggle::make('is_billing_contact')
                    ->label('Billing contact')
                    ->helperText('Sees this client\'s full billing history in the portal — an undesignated contact only sees documents explicitly shared with them.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                TextColumn::make('first_name'),
                TextColumn::make('last_name'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                IconColumn::make('is_primary')->boolean(),
                IconColumn::make('is_billing_contact')
                    ->label('Billing contact')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('generatePortalLink')
                    ->label('Generate portal link')
                    ->icon('heroicon-o-link')
                    ->schema([
                        DatePicker::make('expires_at')
                            ->label('Expires at')
                            ->default(now()->addDays(30))
                            ->native(false),
                    ])
                    ->action(function (Contact $record, array $data) {
                        $link = app(GeneratePortalLink::class)->generate(
                            $record,
                            $data['expires_at'] ? Carbon::parse($data['expires_at']) : null,
                        );

                        try {
                            app(BillingMailer::class)->sendPortalLink($link);
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Portal link created, but the email could not be sent')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()
                            ->success()->seconds(4)
                            ->title('Portal link generated and emailed')
                            ->body(route('portal.client-home', $link))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
