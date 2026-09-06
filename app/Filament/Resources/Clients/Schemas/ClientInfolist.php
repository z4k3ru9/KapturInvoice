<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Grouped into compact, multi-column sections rather than one long stack
 * of full-width label/value rows — condenses the same info into far less
 * vertical space, which also happens to be what a modal-friendly layout
 * needs (see docs/filament-admin-layout-design.md §8 on why Client keeps
 * a View page at all — the Contacts relation manager lives here).
 */
class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('currency_code')->label('Currency')->placeholder('-'),
                        TextEntry::make('tax_number')->label('Tax ID')->placeholder('-'),
                        TextEntry::make('id_number')->placeholder('-'),
                    ]),
                Section::make('Contact')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email')->label('Email address')->placeholder('-'),
                        TextEntry::make('phone')->placeholder('-'),
                        TextEntry::make('website')->placeholder('-'),
                    ]),
                Section::make('Address')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('address_line_1')->label('Address')->columnSpan(2)->placeholder('-'),
                        TextEntry::make('city')->placeholder('-'),
                        TextEntry::make('state')->placeholder('-'),
                        TextEntry::make('address_line_2')->label('Address line 2')->columnSpan(2)->placeholder('-'),
                        TextEntry::make('postal_code')->placeholder('-'),
                        TextEntry::make('country_code')->label('Country')->placeholder('-'),
                    ]),
                Section::make('Billing')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('balance')->numeric(2),
                        TextEntry::make('paid_to_date')->numeric(2),
                        TextEntry::make('default_discount')->label('Default discount')->numeric(2),
                        TextEntry::make('default_discount_is_percentage')
                            ->label('Discount type')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Percentage' : 'Fixed amount'),
                    ]),
                Section::make('Notes')
                    ->visible(fn (Client $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel(),
                    ]),
                Grid::make(3)
                    ->schema([
                        TextEntry::make('legacy_client_id')->numeric()->placeholder('-'),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->visible(fn (Client $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
