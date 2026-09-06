<?php

namespace App\Filament\Resources\ProposalSnippets\Pages;

use App\Filament\Resources\ProposalSnippets\ProposalSnippetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProposalSnippets extends ListRecords
{
    protected static string $resource = ProposalSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
