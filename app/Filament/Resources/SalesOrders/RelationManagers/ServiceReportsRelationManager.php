<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Actions\Delivery\ApproveServiceReport;
use App\Actions\Delivery\CancelServiceReport;
use App\Actions\Delivery\RecordServiceReport;
use App\Actions\Delivery\SubmitServiceReport;
use App\Enums\ServiceReportResult;
use App\Enums\ServiceReportStatus;
use App\Filament\Support\DownloadPdfAction;
use App\Models\SalesOrder;
use App\Models\ServiceReport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
 * "It follows the Delivery Order pattern: multiple per job..." —
 * FINALIZED-DECISIONS.md §10. Mirrors DeliveryOrdersRelationManager/
 * HandoverReportsRelationManager: no generic create/edit/delete, every
 * row is written only through App\Actions\Delivery\RecordServiceReport
 * and progressed only through Submit/Approve/Cancel — but unlike those
 * two (one-shot records), a Service Report carries its own Draft ->
 * Submitted -> Approved lifecycle, so the row actions matter here.
 */
class ServiceReportsRelationManager extends RelationManager
{
    /**
     * Same Filament relation-manager lazy-loading bug documented on
     * DeliveryOrdersRelationManager/HandoverReportsRelationManager — off
     * for the same reason.
     */
    protected static bool $isLazy = false;

    protected static string $relationship = 'serviceReports';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            // Row layout ordered per docs/rebuild/DESIGN.md §4: "the Job
            // page lists Service Reports beside Delivery Orders with the
            // same row layout (date, number, technician, result, status)."
            ->columns([
                TextColumn::make('service_date')->label('Date')->date(),
                TextColumn::make('number')->placeholder('Draft'),
                TextColumn::make('technician.name')->label('Technician')->placeholder(fn (ServiceReport $record) => $record->external_technician_name ?? '-'),
                TextColumn::make('result')->badge()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('createdBy.name')->label('Recorded by')->placeholder('-'),
            ])
            ->recordActions([
                DownloadPdfAction::serviceReport(),
                Action::make('submit')
                    ->label('Submit')
                    ->visible(fn (ServiceReport $record) => $record->status === ServiceReportStatus::Draft)
                    ->action(function (ServiceReport $record) {
                        try {
                            app(SubmitServiceReport::class)->submit($record, Auth::user());
                            Notification::make()->success()->seconds(4)->title('Service report submitted')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not submit service report')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceReport $record) => $record->status === ServiceReportStatus::Submitted)
                    ->action(function (ServiceReport $record) {
                        try {
                            app(ApproveServiceReport::class)->approve($record, Auth::user());
                            Notification::make()->success()->seconds(4)->title('Service report approved')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not approve service report')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label('Reason')->columnSpanFull(),
                    ])
                    ->visible(fn (ServiceReport $record) => in_array($record->status, [ServiceReportStatus::Draft, ServiceReportStatus::Submitted], true))
                    ->action(function (ServiceReport $record, array $data) {
                        try {
                            app(CancelServiceReport::class)->cancel($record, Auth::user(), $data['reason'] ?? null);
                            Notification::make()->success()->seconds(4)->title('Service report cancelled')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not cancel service report')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('recordServiceReport')
                    ->label('Record service report')
                    ->schema([
                        DatePicker::make('service_date')->default(now()),
                        Select::make('technician_user_id')
                            ->label('Technician')
                            ->options(function () {
                                /** @var SalesOrder $salesOrder */
                                $salesOrder = $this->getOwnerRecord();

                                return $salesOrder->company->users()->pluck('name', 'users.id');
                            })
                            ->searchable(),
                        TextInput::make('external_technician_name')
                            ->label('External technician name')
                            ->helperText('For an outsourced or vendor technician, instead of a company user.'),
                        Select::make('result')
                            ->options(ServiceReportResult::class)
                            ->default(ServiceReportResult::Unresolved)
                            ->required(),
                        Textarea::make('problem_reported')->columnSpanFull(),
                        Textarea::make('diagnosis')->columnSpanFull(),
                        Textarea::make('action_taken')->columnSpanFull(),
                        Textarea::make('parts_used')
                            ->label('Parts used')
                            ->helperText('Evidence only — never changes job value. Raise a job variation to bill anything extra.')
                            ->columnSpanFull(),
                        Textarea::make('follow_up_notes')->columnSpanFull(),
                        TextInput::make('customer_acknowledgement_name')->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        /** @var SalesOrder $salesOrder */
                        $salesOrder = $this->getOwnerRecord();

                        try {
                            app(RecordServiceReport::class)->record($salesOrder, Auth::user(), $data);

                            Notification::make()->success()->seconds(4)->title('Service report recorded')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not record service report')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
