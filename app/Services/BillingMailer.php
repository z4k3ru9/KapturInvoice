<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Mail\CompanyTemplatedMail;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

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
    public function __construct(private EmailTemplateRenderer $renderer) {}

    public function sendInvoice(Invoice $invoice): Invitation
    {
        return $this->sendForInvoice($invoice, 'invoice');
    }

    public function sendQuote(Invoice $quote): Invitation
    {
        return $this->sendForInvoice($quote, 'quote');
    }

    public function sendReminder(Invoice $invoice, int $tier): Invitation
    {
        return $this->sendForInvoice($invoice, 'invoice', reminderTier: $tier);
    }

    public function sendPaymentReceipt(Payment $payment): void
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

        Mail::to($contact->email)->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
        ));
    }

    protected function sendForInvoice(Invoice $invoice, string $templateKind, ?int $reminderTier = null): Invitation
    {
        $invoice->loadMissing('client.contacts', 'company.settings');

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

        Mail::to($contact->email)->send(new CompanyTemplatedMail(
            $this->renderer->render($subjectTemplate, $tokens),
            $this->renderer->render($bodyTemplate, $tokens),
        ));

        $invitation->forceFill(['sent_at' => now()])->save();

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->forceFill(['status' => InvoiceStatus::Sent])->saveQuietly();
        }

        return $invitation;
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    protected function resolveContact(?Contact $preferred, $contacts): Contact
    {
        $contact = $preferred ?? $contacts->firstWhere('is_primary', true) ?? $contacts->first();

        if (! $contact || blank($contact->email)) {
            throw new RuntimeException('This client has no contact with an email address to send to.');
        }

        return $contact;
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
        };
    }
}
