<?php

namespace App\Http\Controllers;

use App\Models\StatementOfAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a generated Statement of Account as a PDF, rendered from its
 * frozen `snapshot` (see App\Models\StatementOfAccount,
 * App\Actions\Reports\GenerateStatementOfAccount) — never recomputed
 * live. Mirrors App\Http\Controllers\InvoicePdfController: sits outside
 * the Filament panel, so StatementOfAccount's BelongsToCompany global
 * scope doesn't apply to the route model binding and the tenant check
 * happens explicitly here instead.
 */
class StatementOfAccountPdfController extends Controller
{
    public function __invoke(StatementOfAccount $statementOfAccount): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($statementOfAccount->company), 403);

        $statementOfAccount->loadMissing('client', 'company');

        return Pdf::loadView('pdf.statement-of-account', ['statementOfAccount' => $statementOfAccount])
            ->stream(($statementOfAccount->number ?: 'SOA-preview').'.pdf');
    }
}
