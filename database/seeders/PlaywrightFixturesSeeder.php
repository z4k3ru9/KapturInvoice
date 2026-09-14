<?php

namespace Database\Seeders;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Portal\GeneratePortalLink;
use App\Actions\Portal\RevokePortalLink;
use App\Actions\Receivables\AllocateCustomerPayment;
use App\Actions\Receivables\IssuePaymentReceipt;
use App\Actions\Receivables\RecordCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
 * deterministic fixture data for the Playwright browser suite only. Run
 * exclusively by scripts/browser-test-server.sh against the dedicated
 * database/testing-browser.sqlite, never against a developer's own dev
 * database or in the PHP test suite. "Keep credentials and test data
 * local to the test environment" — nothing here is production data.
 */
class PlaywrightFixturesSeeder extends Seeder
{
    /** @var array<string, mixed> written to storage/app/playwright-fixtures.json for Playwright specs to read directly. */
    private array $manifest = [];

    public function run(): void
    {
        $owner = User::query()->where('email', 'test@example.com')->first();

        foreach (Company::query()->whereIn('slug', ['karunia-abadi', 'axen-technology-indonesia'])->get() as $company) {
            $this->manifest[$company->slug] = $this->seedForCompany($company, $owner);
        }

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/playwright-fixtures.json'), json_encode($this->manifest, JSON_PRETTY_PRINT));
    }

    /** @return array<string, mixed> */
    private function seedForCompany(Company $company, User $owner): array
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Playwright Test Client',
            'email' => 'client@example.test',
            'currency_code' => $company->currency_code ?? 'IDR',
        ]);

        $billingContact = Contact::create([
            'client_id' => $client->id,
            'first_name' => 'Billing',
            'last_name' => 'Contact',
            'email' => 'billing-contact@example.test',
            'is_billing_contact' => true,
        ]);

        $ordinaryContact = Contact::create([
            'client_id' => $client->id,
            'first_name' => 'Ordinary',
            'last_name' => 'Contact',
            'email' => 'ordinary-contact@example.test',
            'is_billing_contact' => false,
        ]);

        // A second, entirely unrelated client — proves an ordinary or
        // billing contact's portal link never leaks a different client's
        // documents within the same company.
        $otherClient = Client::create([
            'company_id' => $company->id,
            'name' => 'Playwright Other Client',
            'currency_code' => $company->currency_code ?? 'IDR',
        ]);

        $invoiceIndonesian = $this->issuedInvoice($company, $client, 'PW-ID-INVOICE', document_language: null);
        $invoiceEnglish = $this->issuedInvoice($company, $client, 'PW-EN-INVOICE', document_language: 'en');
        $this->issuedInvoice($company, $otherClient, 'PW-OTHER-CLIENT-INVOICE', document_language: null);

        // Left in Draft, deliberately not issued — the autosave and
        // dynamic-row-reorder browser journeys (Phase 06B Slice 3/5) both
        // need a document still open for editing.
        $draftInvoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'PW-DRAFT-INVOICE',
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => $company->currency_code ?? 'IDR',
        ]);
        InvoiceItem::create(['invoice_id' => $draftInvoice->id, 'title' => 'Draft line one', 'quantity' => 1, 'unit_cost' => 100000, 'sort_order' => 0]);
        InvoiceItem::create(['invoice_id' => $draftInvoice->id, 'title' => 'Draft line two', 'quantity' => 1, 'unit_cost' => 200000, 'sort_order' => 1]);
        InvoiceItem::create(['invoice_id' => $draftInvoice->id, 'title' => 'Draft line three', 'quantity' => 1, 'unit_cost' => 300000, 'sort_order' => 2]);

        // A second, separate Draft invoice reserved for the two-tab
        // stale-conflict autosave test (tests/browser/documents/
        // autosave.spec.ts) — Playwright's `fullyParallel` mode can run
        // that test concurrently with anything else touching
        // $draftInvoice above (its own sibling autosave test included),
        // and two real, unrelated saves racing the same row's
        // `draft_version` produced a genuine flake, not a bug in the
        // conflict-detection logic itself.
        $draftInvoiceForConflictTest = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'PW-DRAFT-CONFLICT-INVOICE',
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => $company->currency_code ?? 'IDR',
        ]);
        InvoiceItem::create(['invoice_id' => $draftInvoiceForConflictTest->id, 'title' => 'Conflict test line', 'quantity' => 1, 'unit_cost' => 100000, 'sort_order' => 0]);

        // The ordinary contact only ever sees an invoice they have an
        // explicit Invitation for — this one, not $invoiceEnglish.
        Invitation::create([
            'invoice_id' => $invoiceIndonesian->id,
            'contact_id' => $ordinaryContact->id,
        ]);

        // A verified, receipted, allocated payment — SOA and portal
        // balance/receipt journeys both need at least one of these.
        $payment = app(RecordCustomerPayment::class)->record([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'method' => PaymentMethod::BankTransfer,
            'amount' => 1000000,
            'currency_code' => $company->currency_code ?? 'IDR',
            'payment_date' => now()->subDays(5),
            'proof_path' => 'playwright-fixtures/proof.pdf',
        ]);
        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoiceIndonesian->id => 1000000]);
        app(IssuePaymentReceipt::class)->issue($payment->fresh());

        // Portal links: an active one for the billing contact (full
        // history), an active one for the ordinary contact (explicit-share
        // only), an expired one, and a "replaced" one — a link that was
        // superseded by a fresh generate + explicit revoke of the old one,
        // per App\Actions\Portal\GeneratePortalLink's own docblock ("the
        // old one is revoked separately"). Covers every link-state
        // journey Slice 5 requires.
        $activeLink = app(GeneratePortalLink::class)->generate($billingContact);
        $expiredLink = app(GeneratePortalLink::class)->generate($billingContact, now()->subDay());

        $ordinaryActiveLink = app(GeneratePortalLink::class)->generate($ordinaryContact);

        $replacedLink = app(GeneratePortalLink::class)->generate($ordinaryContact);
        app(RevokePortalLink::class)->revoke($replacedLink, $owner, 'Playwright fixture: replaced by a fresh link');

        return [
            'client_id' => $client->id,
            'other_client_id' => $otherClient->id,
            'invoice_id_indonesian' => $invoiceIndonesian->id,
            'invoice_number_indonesian' => $invoiceIndonesian->number,
            'invoice_id_english' => $invoiceEnglish->id,
            'invoice_number_english' => $invoiceEnglish->number,
            'draft_invoice_id' => $draftInvoice->id,
            'draft_invoice_id_for_conflict_test' => $draftInvoiceForConflictTest->id,
            'active_portal_link_key' => $activeLink->key,
            'expired_portal_link_key' => $expiredLink->key,
            'ordinary_active_portal_link_key' => $ordinaryActiveLink->key,
            'replaced_portal_link_key' => $replacedLink->key,
        ];
    }

    private function issuedInvoice(Company $company, Client $client, string $number, ?string $document_language): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => $number,
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => $company->currency_code ?? 'IDR',
            'invoice_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'document_language' => $document_language,
            'terms' => 'Payment due within 30 days.',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Network cabling installation',
            'description' => 'Cat6 structured cabling, 40 points',
            'quantity' => 1,
            'unit_cost' => 15000000,
            'sort_order' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'CCTV camera unit',
            'quantity' => 8,
            'unit_cost' => 1250000,
            'sort_order' => 1,
        ]);

        return app(IssueInvoice::class)->issue($invoice->fresh());
    }
}
