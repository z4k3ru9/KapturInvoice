<?php

namespace App\Filament\Resources\Credits\Pages;

use App\Filament\Resources\Credits\CreditResource;
use App\Filament\Resources\Credits\Tables\CreditsTable;
use App\Models\Credit;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;

class ViewCredit extends ViewRecord
{
    protected static string $resource = CreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            // docs/REFACTOR_PLAN.md drift audit: guards the single-record
            // action the same way CreditsTable::forceDeleteBulkAction()
            // guards the bulk one.
            ForceDeleteAction::make()
                ->before(function (Credit $record) {
                    if (! CreditsTable::isSafeToForceDelete($record)) {
                        Notification::make()
                            ->danger()->persistent()
                            ->title('Could not force-delete credit')
                            ->body('Only a credit never applied against an invoice/payment can be permanently deleted.')
                            ->send();

                        throw new Halt;
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
