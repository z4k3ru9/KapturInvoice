<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\Vendor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('legacy_vendor_id')->numeric()->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('email')->label('Email address')->placeholder('-'),
                TextEntry::make('phone')->placeholder('-'),
                TextEntry::make('website')->placeholder('-'),
                TextEntry::make('address_line_1')->placeholder('-'),
                TextEntry::make('address_line_2')->placeholder('-'),
                TextEntry::make('city')->placeholder('-'),
                TextEntry::make('state')->placeholder('-'),
                TextEntry::make('postal_code')->placeholder('-'),
                TextEntry::make('country_code')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('created_at')->dateTime()->placeholder('-'),
                TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Vendor $record): bool => $record->trashed()),
            ]);
    }
}
