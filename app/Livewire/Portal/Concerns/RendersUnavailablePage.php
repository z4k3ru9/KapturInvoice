<?php

namespace App\Livewire\Portal\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared by every public portal Livewire component
 * (App\Livewire\Portal\ViewInvoice/ClientPortalHome): renders the calm,
 * branded `portal.unavailable` view at a 404 status instead of letting an
 * `abort(404)` fall through to the framework's bare default error page —
 * see that view's own docblock for the full reasoning. This changes only
 * what an already-failed authorization check renders, never the check
 * itself.
 */
trait RendersUnavailablePage
{
    private function abortUnavailable(): never
    {
        throw new HttpResponseException(
            response()->view('portal.unavailable', [
                'company' => app()->bound('currentCompany') ? app('currentCompany') : null,
            ], 404)
        );
    }
}
