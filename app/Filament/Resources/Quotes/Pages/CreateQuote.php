<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\InvoiceType;
use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = InvoiceType::Quote->value;

        return $data;
    }
}
