<?php

namespace App\Filament\Resources\Proposals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProposalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('client.name')->label('Client')->placeholder('-'),
                TextEntry::make('status')->badge(),
                TextEntry::make('amount')->numeric(),
                TextEntry::make('valid_until')->date()->placeholder('-'),
                TextEntry::make('template.name')->label('Template')->placeholder('-'),
                TextEntry::make('invoice.number')->label('Converted to invoice')->placeholder('-'),
                TextEntry::make('sent_at')->dateTime()->placeholder('-'),
                TextEntry::make('viewed_at')->dateTime()->placeholder('-'),
                TextEntry::make('responded_at')->dateTime()->placeholder('-'),
                TextEntry::make('legacy_proposal_id')->numeric()->placeholder('-'),
            ]);
    }
}
