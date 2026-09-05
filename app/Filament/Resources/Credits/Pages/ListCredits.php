<?php

namespace App\Filament\Resources\Credits\Pages;

use App\Filament\Resources\Credits\CreditResource;
use App\Services\DocumentNumberGenerator;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListCredits extends ListRecords
{
    protected static string $resource = CreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Credit has no dedicated Create page (see CreditResource::getPages())
            // — this now opens as a modal, so the number-assignment that used
            // to live in CreateCredit::mutateFormDataBeforeCreate() moves here.
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    if (blank($data['number'] ?? null)) {
                        $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'credit');
                    }

                    return $data;
                }),
        ];
    }
}
