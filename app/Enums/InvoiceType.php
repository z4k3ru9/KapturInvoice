<?php

namespace App\Enums;

enum InvoiceType: string
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
