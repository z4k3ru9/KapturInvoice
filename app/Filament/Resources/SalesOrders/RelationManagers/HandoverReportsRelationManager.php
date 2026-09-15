<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Actions\Delivery\CompleteHandover;
use App\Filament\Support\DownloadPdfAction;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * "Handover Report is required only for installation/service jobs and may
 * require completed delivery ... Admin/Owner overrides require a reason."
 * — Specs.md. Mirrors VariationsRelationManager/DeliveryOrdersRelationManager:
 * no generic create/edit/delete, every row is written only through the
 * "Record handover" action (App\Actions\Delivery\CompleteHandover).
 */
class HandoverReportsRelationManager extends RelationManager
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

    protected static string $relationship = 'handoverReports';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('handover_date')->date(),
                IconColumn::make('is_override')->label('Override')->boolean(),
                TextColumn::make('override_reason')->placeholder('-')->limit(60),
                TextColumn::make('createdBy.name')->label('Recorded by')->placeholder('-'),
            ])
            ->recordActions([
                DownloadPdfAction::handoverReport(),
            ])
            ->headerActions([
                Action::make('recordHandover')
                    ->label('Record handover')
                    ->schema([
                        Textarea::make('notes')->columnSpanFull(),
                        Toggle::make('override')
                            ->label('Override completeness requirement')
                            ->live(),
                        Textarea::make('override_reason')
                            ->label('Override reason')
                            ->required()
                            ->visible(fn (Get $get) => (bool) $get('override'))
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        /** @var SalesOrder $salesOrder */
                        $salesOrder = $this->getOwnerRecord();

                        try {
                            app(CompleteHandover::class)->complete(
                                $salesOrder,
                                Auth::user(),
                                $data['notes'] ?? null,
                                (bool) ($data['override'] ?? false),
                                $data['override_reason'] ?? null,
                            );

                            Notification::make()->success()->seconds(4)->title('Handover recorded')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not record handover')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
