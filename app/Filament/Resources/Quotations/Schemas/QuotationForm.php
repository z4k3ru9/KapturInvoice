<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Enums\PricingMode;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * `status` is deliberately not a form field — every transition goes
 * through App\Actions\Sales\TransitionQuotationStatus/AcceptQuotation
 * (see QuotationsTable's row actions), never a bare edit, so an invalid
 * jump can't be made just by typing a different value in here.
 */
class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quotation')
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($client = Client::find($state)) {
                                    $set('discount', $client->default_discount);
                                    $set('discount_is_percentage', $client->default_discount_is_percentage);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('number')
                            ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                        Select::make('pricing_mode')
                            ->options(PricingMode::class)
                            ->default(PricingMode::Exclusive)
                            ->required(),
                        DatePicker::make('quotation_date'),
                        DatePicker::make('valid_until'),
                        TextInput::make('discount')
                            ->numeric()
                            ->default(0),
                        Toggle::make('discount_is_percentage')
                            ->label('Discount is a percentage'),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('terms')->columnSpanFull(),
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
                Section::make('Totals')
                    ->description('Recomputed automatically from the line items below.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('subtotal')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }
}
