<?php

namespace App\Filament\Widgets;

use App\Filament\Support\SetupChecklist;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The first-run setup checklist, shown only while incomplete — see
 * App\Filament\Support\SetupChecklist and
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md O1.
 * Hides itself once every step is done so a mature tenant's dashboard
 * isn't cluttered with a permanent "all done" banner.
 */
class SetupChecklistWidget extends Widget
{
    protected string $view = 'filament.widgets.setup-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $company = Filament::getTenant();

        return $company && ! SetupChecklist::isComplete($company);
    }

    /**
     * @return array{steps: list<array{key: string, label: string, done: bool, url: string}>, done: int, total: int, progress: string, firstIncompleteIndex: int|null}
     */
    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $checklist = $company ? SetupChecklist::for($company) : ['steps' => [], 'done' => 0, 'total' => 0];

        $firstIncompleteIndex = collect($checklist['steps'])->search(fn (array $step) => ! $step['done']);

        return [
            ...$checklist,
            'progress' => "{$checklist['done']} of {$checklist['total']} completed",
            'firstIncompleteIndex' => $firstIncompleteIndex === false ? null : $firstIncompleteIndex,
        ];
    }
}
