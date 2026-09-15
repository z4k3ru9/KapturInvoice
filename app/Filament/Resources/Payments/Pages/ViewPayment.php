<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            // docs/REFACTOR_PLAN.md drift audit: guards the single-record
            // action the same way PaymentsTable::forceDeleteBulkAction()
            // guards the bulk one.
            ForceDeleteAction::make()
                ->before(function (Payment $record) {
                    if (! PaymentsTable::isSafeToForceDelete($record)) {
                        Notification::make()
                            ->danger()->persistent()
                            ->title('Could not force-delete payment')
                            ->body('Only a Pending payment with no receipt, allocations, or verification events can be permanently deleted.')
                            ->send();

                        throw new Halt;
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
