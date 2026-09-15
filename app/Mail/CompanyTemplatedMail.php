<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;

/**
 * A generic carrier for the rendered subject/body of one of the templates
 * stored on `CompanySetting` (invoice, quote, quotation, payment — see
 * docs/filament-admin-layout-design.md §3.3). Deliberately not
 * queued/subclassed per-template: `App\Services\BillingMailer`/
 * `App\Services\Sales\QuotationMailer` render the plain-text template
 * into a subject/body pair (and, where applicable, the document PDF)
 * before construction, so this class has nothing template-specific left
 * to do.
 */
class CompanyTemplatedMail extends Mailable
{
    /**
     * @param  array<int, Attachment>  $pdfAttachments
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public array $pdfAttachments = [],
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.plain', ['body' => $this->bodyText]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->pdfAttachments;
    }
}
