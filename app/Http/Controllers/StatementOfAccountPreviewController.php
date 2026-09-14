<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\StatementOfAccount;
use App\Services\Reports\BuildStatementOfAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders an ad-hoc, never-persisted Statement of Account preview for a
 * given period — the "A preview may be regenerated before generation"
 * half of docs/rebuild/specs/06b-ux-browser-soa/Specs.md. Computes via
 * App\Services\Reports\BuildStatementOfAccount on every request (unlike
 * StatementOfAccountPdfController, which only ever replays an already
 * frozen `snapshot`) and renders the exact same Blade view against an
 * unsaved `StatementOfAccount` instance that carries no `number`/
 * `generated_at` — the view's own preview banner keys off that.
 *
 * Same "outside the Filament panel, so check the tenant explicitly"
 * pattern as StatementOfAccountPdfController/InvoicePdfController.
 */
class StatementOfAccountPreviewController extends Controller
{
    public function __invoke(Request $request, Client $client, BuildStatementOfAccount $builder): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($client->company), 403);

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'document_language' => ['nullable', Rule::in(['id', 'en'])],
        ]);

        $periodStart = $data['period_start'];
        $periodEnd = $data['period_end'];

        $snapshot = $builder->build($client, $periodStart, $periodEnd);

        $preview = new StatementOfAccount([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'opening_balance' => $snapshot['opening_balance'],
            'closing_balance' => $snapshot['closing_balance'],
            'document_language' => $data['document_language'] ?? null,
            'snapshot' => $snapshot,
        ]);
        $preview->setRelation('company', $client->company);
        $preview->setRelation('client', $client);

        return Pdf::loadView('pdf.statement-of-account', ['statementOfAccount' => $preview])
            ->stream('SOA-preview.pdf');
    }
}
