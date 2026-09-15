<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Proposal as a PDF — same "outside the Filament panel, tenant
 * checked explicitly" pattern as InvoicePdfController/QuotationPdfController.
 * A Proposal's body is free-form HTML/CSS (App\Models\Proposal::$html/
 * $css), so the template just wraps it in a company header rather than
 * rendering a line-item table. Only pictures embedded as a base64 data
 * URI (e.g. via a product-derived Proposal Snippet — see the Products
 * list's "Create proposal snippet" action) are guaranteed to render: an
 * arbitrary <img> uploaded
 * straight into the rich editor pointing at the `local` disk's
 * Storage::url() will not resolve for dompdf, same limitation noted on
 * Company::getLogoDataUri().
 */
class ProposalPdfController extends Controller
{
    public function __invoke(Proposal $proposal): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($proposal->company), 403);

        $proposal->loadMissing('client', 'company');

        return PageNumberFooter::apply(Pdf::loadView('pdf.proposal', ['proposal' => $proposal]))
            ->stream("proposal-{$proposal->id}.pdf");
    }
}
