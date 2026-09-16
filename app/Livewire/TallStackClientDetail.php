<?php

namespace App\Livewire;

use App\Actions\Portal\GeneratePortalLink;
use App\Actions\Portal\RevokePortalLink;
use App\Actions\Reports\GenerateStatementOfAccount;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PortalLink;
use App\Models\Quotation;
use App\Services\BillingMailer;
use App\Support\Dashboard\Money;
use App\Support\Html\RichTextSanitizer;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack client detail page — the sibling page to
 * App\Livewire\TallStackClients. Mirrors the equivalent pre-TallStackUI
 * Filament client resource's infolist plus its Contacts, Portal Links,
 * and Statement of Accounts relation managers field-for-field — no field
 * or action is added or dropped. Every mutation goes through the exact
 * same domain classes those relation managers already called
 * (App\Actions\Portal\GeneratePortalLink/RevokePortalLink,
 * App\Actions\Reports\GenerateStatementOfAccount) — Client/Contact
 * themselves have no dedicated domain action layer in this app (the
 * Filament form/relation manager wrote to them with plain Eloquent
 * create/update, so this page does too).
 *
 * The view groups its Contacts/Portal Links/Statements of Account
 * sections (formerly three separately-stacked <x-card> tables) into an
 * <x-tab> layout (repair-plan Phase 07) — the client name and ledger
 * stats stay pinned above the tabs, never buried inside one, per this
 * project's own earlier QA note that the ledger needs to stay "shown
 * clearly." No render()/mutation logic changed for this pass.
 */
#[Layout('components.tallstack.app')]
class TallStackClientDetail extends Component
{
    use Interactions;

    public Company $company;

    public Client $client;

    // --- Edit-client modal state — same field set as TallStackClients. ---
    public bool $showClientModal = false;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $website = null;

    public ?string $currency_code = null;

    public ?string $tax_number = null;

    public ?string $id_number = null;

    public ?string $legacy_client_id = null;

    public float $default_discount = 0;

    public bool $default_discount_is_percentage = false;

    public ?string $address_line_1 = null;

    public ?string $address_line_2 = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postal_code = null;

    public ?string $country_code = null;

    public ?string $notes = null;

    // --- Contact modal state — mirrors ContactsRelationManager's own form. ---
    public bool $showContactModal = false;

    public ?int $editingContactId = null;

    public ?string $contact_first_name = null;

    public ?string $contact_last_name = null;

    public ?string $contact_email = null;

    public ?string $contact_phone = null;

    public bool $contact_is_primary = false;

    public bool $contact_is_billing_contact = false;

    // --- Statement of Account modal state. ---
    public bool $showSoaModal = false;

    public string $soaPeriodStart;

    public string $soaPeriodEnd;

    public function mount(Company $company, Client $client): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `client` happens before app(Tenancy::class)->set()
        // above activates BelongsToCompany's scope — same explicit guard
        // TallStackQuotationForm uses for its own record parameter.
        abort_unless($client->company_id === $company->id, 404);

        $this->client = $client->loadMissing(['contacts', 'portalLinks.contact', 'statementOfAccounts.generatedBy']);

        $this->soaPeriodStart = now()->startOfMonth()->toDateString();
        $this->soaPeriodEnd = now()->endOfMonth()->toDateString();
    }

    // --- Edit client -----------------------------------------------------

    public function openEditModal(): void
    {
        $this->authorize('update', $this->client);

        $this->name = $this->client->name;
        $this->email = $this->client->email;
        $this->phone = $this->client->phone;
        $this->website = $this->client->website;
        $this->currency_code = $this->client->currency_code;
        $this->tax_number = $this->client->tax_number;
        $this->id_number = $this->client->id_number;
        $this->legacy_client_id = $this->client->legacy_client_id ? (string) $this->client->legacy_client_id : null;
        $this->default_discount = (float) $this->client->default_discount;
        $this->default_discount_is_percentage = (bool) $this->client->default_discount_is_percentage;
        $this->address_line_1 = $this->client->address_line_1;
        $this->address_line_2 = $this->client->address_line_2;
        $this->city = $this->client->city;
        $this->state = $this->client->state;
        $this->postal_code = $this->client->postal_code;
        $this->country_code = $this->client->country_code;
        $this->notes = $this->client->notes;
        $this->showClientModal = true;
    }

    public function saveClient(): void
    {
        $this->authorize('update', $this->client);

        $this->notes = app(RichTextSanitizer::class)->sanitize($this->notes);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'currency_code' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'legacy_client_id' => ['nullable', 'numeric'],
            'default_discount' => ['numeric', 'min:0'],
            'default_discount_is_percentage' => ['boolean'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['legacy_client_id'] = filled($data['legacy_client_id']) ? $data['legacy_client_id'] : null;

        $this->client->update($data);
        $this->client->refresh();

        $this->showClientModal = false;
        $this->toast()->success('Client saved.')->send();
    }

    // --- Contacts ----------------------------------------------------------

    public function addContact(): void
    {
        $this->authorize('update', $this->client);

        $this->resetContactForm();

        // A client's sole contact is always both — see saveContact()'s own
        // enforcement below. Pre-checking here just makes the form honest
        // about what will actually happen for this client's first contact,
        // rather than showing an unchecked box that flips on regardless.
        if ($this->client->contacts->isEmpty()) {
            $this->contact_is_primary = true;
            $this->contact_is_billing_contact = true;
        }

        $this->showContactModal = true;
    }

    public function editContact(int $id): void
    {
        $contact = $this->scopedContact($id);

        if (! $contact) {
            return;
        }

        $this->authorize('update', $this->client);

        $this->editingContactId = $contact->id;
        $this->contact_first_name = $contact->first_name;
        $this->contact_last_name = $contact->last_name;
        $this->contact_email = $contact->email;
        $this->contact_phone = $contact->phone;
        $this->contact_is_primary = (bool) $contact->is_primary;
        $this->contact_is_billing_contact = (bool) $contact->is_billing_contact;
        $this->showContactModal = true;
    }

    public function saveContact(): void
    {
        $this->authorize('update', $this->client);

        $data = $this->validate([
            'contact_first_name' => ['nullable', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_is_primary' => ['boolean'],
            'contact_is_billing_contact' => ['boolean'],
        ]);

        $payload = [
            'first_name' => $data['contact_first_name'],
            'last_name' => $data['contact_last_name'],
            'email' => $data['contact_email'],
            'phone' => $data['contact_phone'],
            'is_primary' => $data['contact_is_primary'],
            'is_billing_contact' => $data['contact_is_billing_contact'],
        ];

        if ($this->editingContactId) {
            $contact = $this->scopedContact($this->editingContactId);

            if ($contact) {
                $contact->update($payload);
            }
        } else {
            $payload['client_id'] = $this->client->id;
            Contact::create($payload);
        }

        $this->client->refresh()->load('contacts');
        $this->enforceSoleContactDefaults();
        $this->showContactModal = false;
        $this->resetContactForm();
        $this->toast()->success('Contact saved.')->send();
    }

    public function deleteContact(int $id): void
    {
        $this->authorize('update', $this->client);

        $contact = $this->scopedContact($id);

        if (! $contact) {
            return;
        }

        $contact->delete();
        $this->client->refresh()->load('contacts');
        $this->enforceSoleContactDefaults();
        $this->toast()->success('Contact removed.')->send();
    }

    /**
     * Same action + mail dispatch as ContactsRelationManager's own
     * "Generate portal link" row action — a portal link is generated
     * from a contact, not the client directly.
     *
     * The generated link is always reachable afterward from the Portal
     * Links tab's own "Copy link" row action (see the view), so this
     * toast's longer timeout is just immediate feedback — not the only
     * way back to the link if the admin misses the default 3s window.
     */
    public function generatePortalLink(int $contactId): void
    {
        $this->authorize('update', $this->client);

        $contact = $this->scopedContact($contactId);

        if (! $contact) {
            return;
        }

        $link = app(GeneratePortalLink::class)->generate($contact, now()->addDays(30));

        try {
            app(BillingMailer::class)->sendPortalLink($link);
            $this->toast()->timeout(10)->success('Portal link generated and emailed.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->timeout(10)->error('Portal link created, but the email could not be sent', $e->getMessage())->send();
        }

        $this->client->refresh()->load('portalLinks.contact');
    }

    private function resetContactForm(): void
    {
        $this->editingContactId = null;
        $this->contact_first_name = null;
        $this->contact_last_name = null;
        $this->contact_email = null;
        $this->contact_phone = null;
        $this->contact_is_primary = false;
        $this->contact_is_billing_contact = false;
    }

    private function scopedContact(int $id): ?Contact
    {
        return $this->client->contacts->firstWhere('id', $id);
    }

    /**
     * A client's sole contact is always both the primary and billing
     * contact by default — with only one point of contact, there's no
     * meaningful "someone else is billing" choice to make, and leaving
     * both unset would silently exclude the client from quote/invoice/
     * reminder emails (App\Services\BillingMailer sends only to
     * designated billing contacts) and from portal billing history. Runs
     * after every contact create/update/delete; a client with two or more
     * contacts is left alone — that's a real choice worth keeping.
     */
    private function enforceSoleContactDefaults(): void
    {
        if ($this->client->contacts->count() !== 1) {
            return;
        }

        $sole = $this->client->contacts->first();

        if (! $sole->is_primary || ! $sole->is_billing_contact) {
            $sole->forceFill(['is_primary' => true, 'is_billing_contact' => true])->save();
            $this->client->refresh()->load('contacts');
        }
    }

    // --- Portal links --------------------------------------------------

    public function revokePortalLink(int $id): void
    {
        $this->authorize('update', $this->client);

        $link = $this->client->portalLinks->firstWhere('id', $id);

        if (! $link) {
            return;
        }

        try {
            app(RevokePortalLink::class)->revoke($link, auth()->user());
            $this->toast()->success('Portal link revoked.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not revoke portal link', $e->getMessage())->send();
        }

        $this->client->refresh()->load('portalLinks.contact');
    }

    // --- Statement of Account --------------------------------------------

    public function openSoaModal(): void
    {
        $this->soaPeriodStart = now()->startOfMonth()->toDateString();
        $this->soaPeriodEnd = now()->endOfMonth()->toDateString();
        $this->showSoaModal = true;
    }

    public function generateStatementOfAccount(): void
    {
        $this->authorize('update', $this->client);

        $data = $this->validate([
            'soaPeriodStart' => ['required', 'date'],
            'soaPeriodEnd' => ['required', 'date', 'after_or_equal:soaPeriodStart'],
        ]);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate(
            $this->client,
            Carbon::parse($data['soaPeriodStart']),
            Carbon::parse($data['soaPeriodEnd']),
            auth()->user(),
        );

        $this->showSoaModal = false;
        $this->toast()->success('Statement of Account generated', "Number: {$statementOfAccount->number}")->send();

        // Redirect into the real document view (App\Livewire\
        // TallStackStatementOfAccount) instead of staying on this page —
        // per docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md's
        // Statement of Accounts item, "Generate" should land on the
        // Issued document, not just toast a number.
        $this->redirect(route('tallstack.clients.statement-of-account', [
            'company' => $this->company,
            'client' => $this->client,
            'statementOfAccount' => $statementOfAccount,
        ]), navigate: false);
    }

    public function render(): View
    {
        $currency = $this->client->currency_code ?: $this->company->currency_code;

        $contacts = $this->client->contacts->map(fn (Contact $contact) => [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email ?? '—',
            'phone' => $contact->phone ?? '—',
            'is_billing_contact' => (bool) $contact->is_billing_contact,
        ]);

        $portalLinks = $this->client->portalLinks->sortByDesc('created_at')->map(function (PortalLink $link) {
            $status = $link->revoked_at !== null
                ? ['label' => 'Revoked', 'color' => 'gray']
                : ($link->expires_at !== null && $link->expires_at->isPast()
                    ? ['label' => 'Expired', 'color' => 'amber']
                    : ['label' => 'Active', 'color' => 'green']);

            return [
                'id' => $link->id,
                'key' => $link->key,
                'contact' => $link->contact?->name ?? '—',
                'created_at' => $link->created_at->format('d M Y'),
                'expires_at' => $link->expires_at?->format('d M Y') ?? 'Never',
                'status_label' => $status['label'],
                'status_color' => $status['color'],
                'revoked' => $link->revoked_at !== null,
            ];
        })->values();

        $statements = $this->client->statementOfAccounts->sortByDesc('generated_at')->map(fn ($soa) => [
            'id' => $soa->id,
            'number' => $soa->number,
            'period' => $soa->period_start->format('d M Y').' – '.$soa->period_end->format('d M Y'),
            'generated_at' => $soa->generated_at?->format('d M Y H:i') ?? '—',
        ])->values();

        // "Total invoiced" mirrors App\Livewire\Portal\ClientPortalHome's
        // CLIENT_VISIBLE_STATUSES — the same set of invoice statuses that
        // were actually issued to the customer, excluding Draft/Approved
        // (never issued) and Cancelled/Void/Amended (stale/superseded).
        $totalInvoiced = (float) Invoice::query()
            ->where('client_id', $this->client->id)
            ->whereIn('status', ['sent', 'viewed', 'partial', 'paid', 'overdue', 'issued'])
            ->sum('total');

        // "Open quotations" — same non-terminal, non-accepted definition
        // TallStackQuotations' own "Active quotations" stat uses.
        $openQuotations = Quotation::query()
            ->where('client_id', $this->client->id)
            ->whereNotIn('status', [
                QuotationStatus::Cancelled->value,
                QuotationStatus::Rejected->value,
                QuotationStatus::Expired->value,
                QuotationStatus::Accepted->value,
            ])
            ->count();

        return view('livewire.tallstack-client-detail', [
            'currency' => $currency,
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
            'contacts' => $contacts,
            'portalLinks' => $portalLinks,
            'statements' => $statements,
            'stats' => [
                'totalInvoiced' => Money::format($totalInvoiced, $currency),
                'totalPaid' => Money::format((float) $this->client->paid_to_date, $currency),
                'outstandingBalance' => Money::format((float) $this->client->balance, $currency),
                'openQuotations' => $openQuotations,
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'clients',
            'title' => $this->client->name,
        ]);
    }
}
