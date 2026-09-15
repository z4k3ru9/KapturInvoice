<?php

namespace App\Services\Concerns;

use App\Models\Contact;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Shared by App\Services\BillingMailer and App\Services\Sales\
 * QuotationMailer — both need the exact same contact precedence
 * (preferred contact -> billing contact -> primary contact -> first
 * contact) when picking who a document email goes to.
 */
trait ResolvesBillingContact
{
    /**
     * @param  Collection<int, Contact>  $contacts
     */
    protected function resolveContact(?Contact $preferred, Collection $contacts): Contact
    {
        $contact = $preferred ?? $contacts->firstWhere('is_billing_contact', true) ?? $contacts->firstWhere('is_primary', true) ?? $contacts->first();

        if (! $contact || blank($contact->email)) {
            throw new RuntimeException('This client has no contact with an email address to send to.');
        }

        return $contact;
    }
}
