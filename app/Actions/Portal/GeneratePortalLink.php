<?php

namespace App\Actions\Portal;

use App\Models\Contact;
use App\Models\PortalLink;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Mints a new `PortalLink` for a contact — per
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md's "replaceable"
 * requirement, generating a new link for a contact that already has an
 * active one is always allowed; the old one is revoked separately (see
 * `RevokePortalLink`) rather than through a combined action.
 *
 * No role gate: Specs.md doesn't restrict who may generate a link, only
 * what a contact sees once inside one.
 */
class GeneratePortalLink
{
    public function generate(Contact $contact, ?CarbonInterface $expiresAt = null): PortalLink
    {
        if (! $contact->client) {
            throw new RuntimeException('This contact has no client to generate a portal link for.');
        }

        return PortalLink::create([
            'company_id' => $contact->client->company_id,
            'client_id' => $contact->client_id,
            'contact_id' => $contact->id,
            'expires_at' => $expiresAt ?? now()->addDays(30),
        ]);
    }
}
