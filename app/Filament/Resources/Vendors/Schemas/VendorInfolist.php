<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\Vendor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->columnSpanFull(),
                        TextEntry::make('email')->label('Email address')->placeholder('-'),
                        TextEntry::make('phone')->placeholder('-'),
                        TextEntry::make('website')->placeholder('-'),
                    ]),
                Section::make('Address')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address_line_1')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('address_line_2')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('city')->placeholder('-'),
                        TextEntry::make('state')->placeholder('-'),
                        TextEntry::make('postal_code')->placeholder('-'),
                        TextEntry::make('country_code')->label('Country code')->placeholder('-'),
                    ]),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                Section::make('Record')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('legacy_vendor_id')->numeric()->placeholder('-'),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->visible(fn (Vendor $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
