<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'quote');
        }

        return $data;
    }
}
