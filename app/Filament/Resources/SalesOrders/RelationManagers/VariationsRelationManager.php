<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Actions\Sales\ApproveJobVariation;
use App\Enums\JobVariationType;
use App\Models\SalesOrder;
use Filament\Actions\Action;
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
 * "Support overrun, out-of-scope work, and item substitutions only
 * through Owner/Admin approval with reason. Preserve original approved
 * values and variation history." — Specs.md. There is deliberately no
 * generic create/edit/delete here: every row is written only through the
 * "Record variation" action below (App\Actions\Sales\ApproveJobVariation),
 * which is the only path that checks the approver's role, advances the
 * job's approved value, and writes the audit event together.
 */
class VariationsRelationManager extends RelationManager
{
    protected static string $relationship = 'variations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('reason')->limit(60),
                TextColumn::make('amount')->numeric(),
                TextColumn::make('value_before')->numeric(),
                TextColumn::make('value_after')->numeric(),
                TextColumn::make('approvedBy.name')->label('Approved by')->placeholder('-'),
                TextColumn::make('approved_at')->dateTime(),
            ])
            ->headerActions([
                Action::make('recordVariation')
                    ->label('Record variation')
                    ->schema([
                        Select::make('type')
                            ->options(JobVariationType::class)
                            ->required(),
                        Textarea::make('reason')->required()->columnSpanFull(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->helperText('Signed delta to the job value — positive for an overrun/added scope, negative for a reduction.'),
                    ])
                    ->action(function (array $data) {
                        /** @var SalesOrder $salesOrder */
                        $salesOrder = $this->getOwnerRecord();

                        try {
                            app(ApproveJobVariation::class)->approve(
                                $salesOrder,
                                Auth::user(),
                                JobVariationType::from($data['type']),
                                $data['reason'],
                                (float) $data['amount'],
                            );

                            Notification::make()->success()->title('Variation approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Could not approve variation')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
