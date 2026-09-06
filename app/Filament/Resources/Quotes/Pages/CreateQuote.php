<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\InvoiceType;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = InvoiceType::Quote->value;

        if (blank($data['number'] ?? null)) {
            $data['number'] = app(DocumentNumberGenerator::class)->next(Filament::getTenant(), 'quote');
        }

        return $data;
    }
}
