<?php

namespace App\Filament\Resources\Credits\Schemas;

use App\Models\Credit;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CreditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')
                    ->label('Client'),
                TextEntry::make('invoice.number')
                    ->label('Applied to invoice')
                    ->placeholder('-'),
                TextEntry::make('legacy_credit_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('balance')
                    ->numeric(),
                TextEntry::make('credit_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('public_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('private_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Credit $record): bool => $record->trashed()),
            ]);
    }
}
