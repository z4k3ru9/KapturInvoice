<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Actions\Delivery\CompleteDelivery;
use App\Filament\Support\DownloadPdfAction;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * "Delivery Order applies to all applicable delivery events, including
 * delivery-only jobs ... multiple partial Delivery Orders are allowed." —
 * Specs.md. Mirrors VariationsRelationManager: no generic create/edit/
 * delete, every row is written only through the "Record delivery" action
 * (App\Actions\Delivery\CompleteDelivery).
 */
class DeliveryOrdersRelationManager extends RelationManager
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

    protected static string $relationship = 'deliveryOrders';

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
                TextColumn::make('delivery_date')->date(),
                TextColumn::make('notes')->placeholder('-')->limit(60),
                TextColumn::make('createdBy.name')->label('Recorded by')->placeholder('-'),
            ])
            ->recordActions([
                DownloadPdfAction::deliveryOrder(),
            ])
            ->headerActions([
                Action::make('recordDelivery')
                    ->label('Record delivery')
                    ->schema([
                        Textarea::make('notes')->columnSpanFull(),
                        Repeater::make('items')
                            ->schema([
                                Select::make('sales_order_item_id')
                                    ->label('Job line')
                                    ->options(function () {
                                        /** @var SalesOrder $salesOrder */
                                        $salesOrder = $this->getOwnerRecord();

                                        return $salesOrder->items->pluck('title', 'id');
                                    })
                                    ->searchable(),
                                TextInput::make('description'),
                                TextInput::make('quantity_delivered')->numeric()->required(),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        /** @var SalesOrder $salesOrder */
                        $salesOrder = $this->getOwnerRecord();

                        try {
                            app(CompleteDelivery::class)->complete(
                                $salesOrder,
                                Auth::user(),
                                self::mapItems($data['items']),
                                $data['notes'] ?? null,
                            );

                            Notification::make()->success()->seconds(4)->title('Delivery recorded')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not record delivery')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{sales_order_item_id: ?int, description: ?string, quantity_delivered: float}>
     */
    private static function mapItems(array $items): array
    {
        return array_map(fn (array $item) => [
            'sales_order_item_id' => filled($item['sales_order_item_id'] ?? null) ? (int) $item['sales_order_item_id'] : null,
            'description' => $item['description'] ?? null,
            'quantity_delivered' => (float) $item['quantity_delivered'],
        ], $items);
    }
}
