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
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.plain', ['body' => $this->bodyText]);

        // Only overrides the global config('mail.from') default when the
        // sending company has its own CompanySetting::mail_config on file
        // (App\Services\CompanyMailerResolver) — most sends still use the
        // app-wide default untouched.
        if (filled($this->fromAddress)) {
            $mail->from($this->fromAddress, $this->fromName);
        }

        return $mail;
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->pdfAttachments;
    }
}
