<?php

namespace App\Filament\Resources\ProposalSnippets\Pages;

use App\Filament\Resources\ProposalSnippets\ProposalSnippetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProposalSnippet extends EditRecord
{
    protected static string $resource = ProposalSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
