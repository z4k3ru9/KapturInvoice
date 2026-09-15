<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Mail\CompanyTemplatedMail;
use App\Models\CompanySetting;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PortalLink;
use App\Models\StatementOfAccount;
use App\Services\Concerns\ResolvesBillingContact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;

/**
 * Closes the gap flagged in docs/filament-admin-layout-design.md §3.3 —
 * the invoice/quote/payment templates and reminder schedule stored on
 * `CompanySetting` are now actually dispatched, not just stored config.
 *
 * Sending an invoice/quote reuses (or creates) the client contact's
 * `Invitation` — the same row the public portal page (§2.2) resolves by
 * its `key` — so the emailed link and the admin's "Copy portal link"
 * action always point at the same place.
 */
class BillingMailer
{
    use ResolvesBillingContact;

    public function __construct(private EmailTemplateRenderer $renderer) {}

    /**
     * @param  array<int, string>  $cc
     */
    public function sendInvoice(Invoice $invoice, array $cc = []): Invitation
    {
        return $this->sendForInvoice($invoice, 'invoice', cc: $cc);
    }

    /**
     * @param  array<int, string>  $cc
     */
    public function sendQuote(Invoice $quote, array $cc = []): Invitation
    {
        return $this->sendForInvoice($quote, 'quote', cc: $cc);
    }

    public function sendReminder(Invoice $invoice, int $tier): Invitation
    {
        return $this->sendForInvoice($invoice, 'invoice', reminderTier: $tier);
    }

    /**
     * @param  array<int, string>  $cc
     */
    public function sendPaymentReceipt(Payment $payment, array $cc = []): void
    {
        $payment->loadMissing('client.contacts', 'company.settings', 'invoice');

        $contact = $this->resolveContact($payment->contact, $payment->client->contacts);

        [$subjectTemplate, $bodyTemplate] = $this->templateFor($payment->company->settings, 'payment');

        $tokens = [
            '{{client_name}}' => $payment->client->name,
            '{{contact_name}}' => $contact->name,
            '{{company_name}}' => $payment->company->name,
            '{{invoice_number}}' => $payment->invoice?->number ?? '',
            '{{amount}}' => number_format((float) $payment->amount, 2),
        ];

        Mail::to($contact->email)->cc(array_values($cc))->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
        ));
    }

    /**
     * Emails the broader, contact-scoped `PortalLink` (§5 of
     * FINALIZED-DECISIONS.md) to its own contact — separate from
     * `sendForInvoice()`'s `Invitation`-based flow above, since a portal
     * link isn't tied to one invoice.
     */
    public function sendPortalLink(PortalLink $link): void
    {
        $link->loadMissing('client', 'contact', 'company.settings');

        $contact = $this->resolveContact($link->contact, collect([$link->contact]));

        [$subjectTemplate, $bodyTemplate] = $this->templateFor($link->company->settings, 'portal_link');

        $tokens = [
            '{{client_name}}' => $link->client->name,
            '{{contact_name}}' => $contact->name,
            '{{company_name}}' => $link->company->name,
            '{{portal_link}}' => route('portal.client-home', $link),
            '{{expires_at}}' => $link->expires_at?->toFormattedDateString() ?? 'never',
        ];

        Mail::to($contact->email)->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
        ));
    }

    /**
     * Emails an already-Issued `StatementOfAccount`'s frozen PDF to the
     * client's resolved billing contact — same "no dedicated
     * CompanySetting template column, hardcoded subject/body" pattern as
     * `sendPortalLink()` above (an SOA is not one of the four templates
     * stored on `CompanySetting`), reusing `pdf.statement-of-account`
     * (the exact same view `App\Http\Controllers\
     * StatementOfAccountPdfController` renders) rather than a second copy.
     * Never called for a Preview — a Preview has no `number`/persisted
     * row to attach.
     */
    public function sendStatementOfAccount(StatementOfAccount $statementOfAccount): void
    {
        $statementOfAccount->loadMissing('client.contacts', 'company.settings');

        $contact = $this->resolveContact(null, $statementOfAccount->client->contacts);

        [$subjectTemplate, $bodyTemplate] = $this->templateFor($statementOfAccount->company->settings, 'statement_of_account');

        $tokens = [
            '{{client_name}}' => $statementOfAccount->client->name,
            '{{contact_name}}' => $contact->name,
            '{{company_name}}' => $statementOfAccount->company->name,
            '{{soa_number}}' => (string) $statementOfAccount->number,
            '{{closing_balance}}' => number_format((float) $statementOfAccount->closing_balance, 2),
        ];

        $pdf = Pdf::loadView('pdf.statement-of-account', ['statementOfAccount' => $statementOfAccount])->output();

        Mail::to($contact->email)->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
            [Attachment::fromData(fn () => $pdf, "{$statementOfAccount->number}.pdf")->withMime('application/pdf')],
        ));
    }

    /**
     * @param  array<int, string>  $cc
     */
    protected function sendForInvoice(Invoice $invoice, string $templateKind, ?int $reminderTier = null, array $cc = []): Invitation
    {
        $invoice->loadMissing('client.contacts', 'company.settings', 'items');

        $contact = $this->resolveContact(null, $invoice->client->contacts);

        $invitation = Invitation::query()->firstOrCreate([
            'invoice_id' => $invoice->id,
            'contact_id' => $contact->id,
        ]);

        [$subjectTemplate, $bodyTemplate] = $this->templateFor($invoice->company->settings, $templateKind);

        if ($reminderTier) {
            $subjectTemplate = "Reminder: {$subjectTemplate}";
        }

        $tokens = [
            '{{client_name}}' => $invoice->client->name,
            '{{contact_name}}' => $contact->name,
            '{{company_name}}' => $invoice->company->name,
            '{{invoice_number}}' => (string) $invoice->number,
            '{{amount}}' => number_format((float) $invoice->total, 2),
            '{{balance}}' => number_format((float) $invoice->balance, 2),
            '{{due_date}}' => $invoice->due_date?->toFormattedDateString() ?? '',
            '{{portal_link}}' => url('/portal/'.$invitation->key),
        ];

        // Reuses InvoicePdfController's exact rendering — same view, same
        // loaded relations — rather than a second copy of the Blade
        // template. The same `pdf.invoice` view already prints either
        // document type correctly (InvoiceType::Invoice/Quote), since
        // both live on the same `invoices` table.
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output();

        Mail::to($contact->email)->cc(array_values($cc))->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
            [Attachment::fromData(fn () => $pdf, "{$invoice->number}.pdf")->withMime('application/pdf')],
        ));

        $invitation->forceFill(['sent_at' => now()])->save();

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->forceFill(['status' => InvoiceStatus::Sent])->saveQuietly();
        }

        return $invitation;
    }

    /**
     * @return array{0: string, 1: string} [subject template, body template]
     */
    protected function templateFor(?CompanySetting $settings, string $kind): array
    {
        return match ($kind) {
            'invoice' => [
                $settings?->invoice_email_subject ?: 'Invoice {{invoice_number}} from {{company_name}}',
                $settings?->invoice_email_body ?: "Hi {{contact_name}},\n\nYour invoice {{invoice_number}} for {{amount}} is ready. You can view it online here:\n{{portal_link}}\n\nThanks,\n{{company_name}}",
            ],
            'quote' => [
                $settings?->quote_email_subject ?: 'Quote {{invoice_number}} from {{company_name}}',
                $settings?->quote_email_body ?: "Hi {{contact_name}},\n\nPlease review your quote {{invoice_number}} for {{amount}}:\n{{portal_link}}\n\nThanks,\n{{company_name}}",
            ],
            'payment' => [
                $settings?->payment_email_subject ?: 'Payment received — {{invoice_number}}',
                $settings?->payment_email_body ?: "Hi {{contact_name}},\n\nWe've received your payment of {{amount}} for invoice {{invoice_number}}. Thank you!\n\n{{company_name}}",
            ],
            'portal_link' => [
                'Your billing portal link from {{company_name}}',
                "Hi {{contact_name}},\n\nYou can view {{client_name}}'s billing history here:\n{{portal_link}}\n\nThis link expires on {{expires_at}}.\n\nThanks,\n{{company_name}}",
            ],
            'statement_of_account' => [
                'Statement of Account {{soa_number}} from {{company_name}}',
                "Hi {{contact_name}},\n\nPlease find attached {{client_name}}'s Statement of Account {{soa_number}}. Closing balance: {{closing_balance}}.\n\nThanks,\n{{company_name}}",
            ],
        };
    }
}
