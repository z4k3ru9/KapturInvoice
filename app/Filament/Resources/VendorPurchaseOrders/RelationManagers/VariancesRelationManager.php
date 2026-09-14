<?php

namespace App\Filament\Resources\VendorPurchaseOrders\RelationManagers;

use App\Actions\Procurement\ApproveVendorPoVariance;
use App\Models\VendorPurchaseOrder;
use Filament\Actions\Action;
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
 * "A Vendor PO variance blocks payment above the approved PO amount until
 * Admin/Owner approval with reason" — Specs.md. Mirrors
 * App\Filament\Resources\SalesOrders\RelationManagers\VariationsRelationManager:
 * no generic create/edit/delete, every row is written only through the
 * "Record variance" action (App\Actions\Procurement\ApproveVendorPoVariance),
 * which checks the approver's role and raises the PO's payment ceiling.
 */
class VariancesRelationManager extends RelationManager
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

    protected static string $relationship = 'variances';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('reason')->limit(60),
                TextColumn::make('amount')->numeric(),
                TextColumn::make('approvedBy.name')->label('Approved by')->placeholder('-'),
                TextColumn::make('approved_at')->dateTime(),
            ])
            ->headerActions([
                Action::make('recordVariance')
                    ->label('Record variance')
                    ->schema([
                        Textarea::make('reason')->required()->columnSpanFull(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->helperText('Must be positive — raises the payment ceiling.'),
                    ])
                    ->action(function (array $data) {
                        /** @var VendorPurchaseOrder $po */
                        $po = $this->getOwnerRecord();

                        try {
                            app(ApproveVendorPoVariance::class)->approve(
                                $po,
                                Auth::user(),
                                $data['reason'],
                                (float) $data['amount'],
                            );

                            Notification::make()->success()->seconds(4)->title('Variance approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not approve variance')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
