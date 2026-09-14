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

                            Notification::make()->success()->title('Handover recorded')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not record handover')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
