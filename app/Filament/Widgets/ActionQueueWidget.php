<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ActionQueue;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Role-aware "Action queue" — see App\Filament\Support\ActionQueue for the
 * per-role item logic and docs/rebuild/DESIGN.md §3 for the approved
 * queue contents. Auditor sees the same items, read-only (no links).
 */
class ActionQueueWidget extends Widget
{
    protected string $view = 'filament.widgets.action-queue';

    protected int|string|array $columnSpan = ['lg' => 3];

    // See RevenueOverview's $isLazy for why.
    protected static bool $isLazy = false;

    /**
     * @return array{items: list<array{label: string, count: int, url: ?string, tone: string}>}
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $company = Filament::getTenant();

        $items = ($user && $company) ? ActionQueue::for($user, $company) : [];

        return ['items' => $items];
    }
}
