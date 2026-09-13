<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which Company/entity's public homepage is being served by
 * matching the request's host against `companies.domain` — the two
 * businesses KapturInvoice runs each get their own domain, sharing this
 * codebase (see docs/invoiceninja-v4-schema-reference.md §4). This is
 * deliberately separate from Filament's tenancy: the admin panel resolves
 * its tenant from the URL path (/admin/{tenant}), the public site resolves
 * it from the Host header.
 *
 * Falls back to the first configured company in local/testing so the
 * homepage is reachable during development without editing /etc/hosts —
 * never in production, where an unmatched domain is a real 404.
 */
class ResolveCompanyFromDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = preg_replace('/^www\./', '', $request->getHost());

        $company = Company::query()->active()->where('domain', $host)->first();

        if (! $company && app()->environment(['local', 'testing'])) {
            $company = Company::query()->active()->orderBy('id')->first();
        }

        // A disabled company must not be reachable through a stale/known
        // domain, a portal link, or a PDF download — reject it exactly
        // like an unknown host rather than exposing that it exists.
        abort_unless($company, 404);

        // Bound into the container (not just set on the request attributes)
        // so it's reliably reachable from Livewire::test(), which mounts
        // components in-process rather than through a fresh HTTP request.
        app()->instance('currentCompany', $company);
        View::share('company', $company);

        return $next($request);
    }
}
