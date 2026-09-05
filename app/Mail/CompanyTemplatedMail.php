<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * A generic carrier for the rendered subject/body of one of the templates
 * stored on `CompanySetting` (invoice, quote, payment — see
 * docs/filament-admin-layout-design.md §3.3). Deliberately not
 * queued/subclassed per-template: `App\Services\BillingMailer` renders the
 * plain-text template into a subject/body pair before construction, so
 * this class has nothing template-specific left to do.
 */
class CompanyTemplatedMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.plain', ['body' => $this->bodyText]);
    }
}
