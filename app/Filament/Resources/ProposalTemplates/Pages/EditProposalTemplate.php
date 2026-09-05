<?php

namespace App\Filament\Resources\ProposalTemplates\Pages;

use App\Filament\Resources\ProposalTemplates\ProposalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProposalTemplate extends EditRecord
{
    protected static string $resource = ProposalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
