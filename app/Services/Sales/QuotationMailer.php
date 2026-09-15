<?php

namespace App\Services\Sales;

use App\Mail\CompanyTemplatedMail;
use App\Models\Quotation;
use App\Services\Concerns\ResolvesBillingContact;
use App\Services\EmailTemplateRenderer;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a job-centric `App\Models\Quotation` (Phase 03) with its PDF
 * attached — the sibling of `App\Services\BillingMailer` kept in its own
 * `Sales` namespace, mirroring how `App\Actions\Sales\*` is already kept
 * separate from `App\Actions\Billing\*` for the same reason: a Quotation
 * is not the legacy `Invoice`/`type=quote` row `BillingMailer::sendQuote()`
 * handles, and doesn't share its lifecycle.
 *
 * Unlike an Invoice/legacy Quote, a Quotation has no `Invitation`/
 * `PortalLink` wiring today — there is no public portal route to view one
 * online (grepped, confirmed absent). So this deliberately emails the PDF
 * as an attachment only, with no "view online" link in the body — adding
 * a portal route for Quotation is out of scope here (see the class this
 * mailer's tests live beside for the note).
 */
class QuotationMailer
{
    use ResolvesBillingContact;

    public function __construct(private EmailTemplateRenderer $renderer) {}

    /**
     * @param  array<int, string>  $cc
     */
    public function sendQuotation(Quotation $quotation, array $cc = []): void
    {
        $quotation->loadMissing('client.contacts', 'company.settings', 'items.product');

        $contact = $this->resolveContact(null, $quotation->client->contacts);

        $settings = $quotation->company->settings;

        $subjectTemplate = $settings?->quotation_email_subject
            ?: 'Quotation {{quotation_number}} from {{company_name}}';
        $bodyTemplate = $settings?->quotation_email_body
            ?: "Hi {{contact_name}},\n\nPlease find your quotation {{quotation_number}} for {{amount}} attached.\n\nThanks,\n{{company_name}}";

        $tokens = [
            '{{client_name}}' => $quotation->client->name,
            '{{contact_name}}' => $contact->name,
            '{{company_name}}' => $quotation->company->name,
            '{{quotation_number}}' => (string) $quotation->number,
            '{{amount}}' => number_format((float) $quotation->total, 2),
        ];

        // Reuses QuotationPdfController's exact rendering — same view,
        // same loaded relations — rather than a second copy of the
        // Blade template.
        $pdf = PageNumberFooter::apply(Pdf::loadView('pdf.quotation', ['quotation' => $quotation]))->output();

        Mail::to($contact->email)->cc(array_values($cc))->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
            [Attachment::fromData(fn () => $pdf, "{$quotation->number}.pdf")->withMime('application/pdf')],
        ));
    }
}
