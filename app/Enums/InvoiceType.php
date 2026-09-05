<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasLabel
{
    case Invoice = 'invoice';
    case Quote = 'quote';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Quote => 'Quote',
        };
    }
}
