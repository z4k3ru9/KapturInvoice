<?php

namespace App\Filament\Resources\Credits\Pages;

use App\Filament\Resources\Credits\CreditResource;
use Filament\Resources\Pages\ListRecords;

class ListCredits extends ListRecords
{
    protected static string $resource = CreditResource::class;

    /**
     * "New credit-note creation... remain deferred" —
     * FINALIZED-DECISIONS.md §7 (docs/REFACTOR_PLAN.md drift audit: this
     * page previously kept a fully working CreateAction minting real `CR`
     * numbers despite that decision — hiding the nav entry alone
     * (CreditResource::shouldRegisterNavigation()) doesn't disable a
     * List page's own header action). Existing/legacy-imported credits
     * stay viewable and editable via the table's own EditAction.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
