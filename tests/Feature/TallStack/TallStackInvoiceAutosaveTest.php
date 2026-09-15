<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Livewire\TallStackInvoiceForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Autosave is draft-only, batched, debounced, conflict-aware, and never
 * performs a financial action... Add server version or equivalent
 * optimistic concurrency protection... Preserve local values after failed
 * save and expose explicit retry." Ported from the deleted pre-TallStackUI
 * Filament admin's own AutosavesDraft coverage to exercise the
 * TALL-stack replacement, App\Livewire\TallStackInvoiceForm +
 * App\Livewire\Concerns\AutosavesDraft, the reference implementation for
 * this app's plain-Livewire form shape.
 */
class TallStackInvoiceAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        return $user;
    }

    private function draftInvoice(): Invoice
    {
        // discount_is_percentage is explicit here (rather than relying on
        // the DB column default) since Invoice::create() never re-fetches
        // the row afterward — an omitted value would leave the in-memory
        // model's boolean-cast attribute null, which TallStackInvoiceForm's
        // mount() then can't assign to its typed `bool $discount_is_percentage`
        // property.
        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-DRAFT',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
        ]);
    }

    public function test_autosave_persists_a_draft_field_and_advances_the_version(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();

        // Setting the field alone is enough — the field is wired
        // `wire:model.live.debounce.1750ms` with an `updatedTerms()` hook
        // that calls autosaveDraft(), so this exercises the real
        // end-to-end wiring (field change -> updated hook -> autosaveDraft()),
        // not just a raw method call.
        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', 'Net 30');

        $component->assertSet('autosaveStatus', 'saved');

        $fresh = $invoice->fresh();
        $this->assertSame('Net 30', $fresh->terms);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_a_stale_autosave_is_rejected_as_a_conflict_not_silently_merged(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();

        // Simulate another tab/user autosaving first, advancing the
        // server's draft_version past what this page still knows about.
        $invoice->forceFill(['terms' => 'Someone else typed this', 'draft_version' => 1])->save();

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice]);
        // The component mounted and cached draft_version = 0 *before* the
        // line above ran in a real scenario; here we force that stale
        // state directly for a deterministic test.
        $component->set('autosaveKnownVersion', 0)
            ->set('terms', 'My own edit')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict');

        // Never silently overwritten — the server's value from the other
        // session is untouched.
        $this->assertSame('Someone else typed this', $invoice->fresh()->terms);
        $this->assertNotNull($component->get('autosaveConflictFields'));
        $this->assertArrayHasKey('terms', $component->get('autosaveConflictFields'));
    }

    public function test_discarding_a_conflict_adopts_the_servers_values(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['terms' => 'Server value', 'draft_version' => 1])->save();

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('autosaveKnownVersion', 0)
            ->set('terms', 'My local edit')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict')
            ->call('discardAutosaveConflict');

        $component->assertSet('autosaveStatus', 'idle')
            ->assertSet('terms', 'Server value')
            ->assertSet('autosaveKnownVersion', 1);
    }

    public function test_overwriting_a_conflict_forces_the_local_edit_through(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['terms' => 'Server value', 'draft_version' => 1])->save();

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('autosaveKnownVersion', 0)
            ->set('terms', 'My local edit, keep it')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict')
            ->call('overwriteAutosaveConflict');

        $component->assertSet('autosaveStatus', 'saved');
        $this->assertSame('My local edit, keep it', $invoice->fresh()->terms);
        $this->assertSame(2, $invoice->fresh()->draft_version);
    }

    public function test_a_failed_autosave_preserves_local_values_for_retry(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();

        // Simulate a genuine save-time failure (schema drift, DB outage, a
        // dropped column) with a real, self-contained schema change rather
        // than an Eloquent event listener — the primary autosave path is a
        // raw query-builder update(), which never fires model events
        // anyway, and this must be a real failure the underlying UPDATE
        // statement hits. Restored in `finally`.
        Schema::table('invoices', function ($table) {
            $table->renameColumn('terms', 'terms_temporarily_renamed');
        });

        try {
            $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
                ->set('terms', 'Typed while offline')
                ->call('autosaveDraft');

            $component->assertSet('autosaveStatus', 'failed')
                ->assertSet('terms', 'Typed while offline');

            // Explicit retry is available and re-attempts the same save
            // (still fails here, since the schema is still broken — the
            // point is that it's available and doesn't throw/crash the
            // page).
            $component->call('retryAutosaveDraft')
                ->assertSet('autosaveStatus', 'failed');
        } finally {
            Schema::table('invoices', function ($table) {
                $table->renameColumn('terms_temporarily_renamed', 'terms');
            });
        }

        $this->assertNull($invoice->fresh()->terms);
    }

    public function test_autosave_never_runs_once_the_invoice_leaves_draft_status(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['status' => InvoiceStatus::Issued])->save();

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', 'Trying to edit an issued invoice')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'idle');
        $this->assertNotSame('Trying to edit an issued invoice', $invoice->fresh()->terms);
    }

    public function test_autosave_never_touches_status_or_derived_totals(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['total' => 500, 'balance' => 500])->save();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', 'A perfectly normal draft edit')
            ->call('autosaveDraft');

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Draft, $fresh->status);
        $this->assertSame('500.00', $fresh->total);
        $this->assertSame('500.00', $fresh->balance);
    }

    /**
     * Not in the original Filament coverage — added here because the
     * TALL-stack port's autosaveGuard() now explicitly re-checks the same
     * Gate::allows('update', ...) boundary save() already enforced,
     * closing a side channel a read-only role (e.g. Auditor) could
     * otherwise reach through autosave alone even though it can open this
     * page and type into the field.
     */
    public function test_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', 'An auditor should not be able to save this')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertNull($invoice->fresh()->terms);
        $this->assertSame(0, $invoice->fresh()->draft_version);
    }

    /**
     * Terms/footer/notes moved from a plain <x-textarea> to TallStackUI's
     * <x-editor> (see resources/views/livewire/tallstack-invoice-form.blade.php).
     * <x-editor> doesn't honor wire:model's .live/.debounce modifiers (only
     * .live/.blur — TallStackUI\Support\Blade\Wireable::entangle()), and
     * Livewire refuses calling an updated{Field}() lifecycle hook directly
     * from Alpine, so the blade view instead wires
     * x-on:editor:change.debounce.1750ms="$wire.$commit()" — pushing the
     * already-entangled value after the same 1750ms pause, which makes
     * Livewire's own dirty-check fire updatedTerms() automatically, exactly
     * as it does for any other property change. That JS wiring can't run in
     * a PHPUnit test, but Livewire::test()->set() exercises the identical
     * server-side path (the property changes, updatedTerms() fires, which
     * sanitizes and then calls autosaveDraft()) — this is the same
     * assertion every other test in this file already makes, extended here
     * to confirm real formatted HTML content (not just plain text) survives
     * the round trip.
     */
    public function test_autosave_persists_formatted_html_content_from_the_editor(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();

        $formatted = '<p>Payment is due <strong>within 30 days</strong>.</p><ul><li>No refunds</li></ul>';

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', $formatted);

        $component->assertSet('autosaveStatus', 'saved');
        $this->assertSame($formatted, $invoice->fresh()->terms);
    }

    /**
     * The editor sanitizes in the browser, but per its own docs that isn't
     * the defense — App\Support\Html\RichTextSanitizer strips anything a
     * hand-crafted payload bypassing the browser could still carry, on
     * every save path including autosave's updatedTerms() hook.
     */
    public function test_autosave_sanitizes_a_malicious_payload_before_persisting(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftInvoice();

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('terms', '<p onmouseover="alert(1)">Net 30</p><script>alert("xss")</script>');

        $component->assertSet('autosaveStatus', 'saved');

        $fresh = $invoice->fresh()->terms;
        $this->assertSame('<p>Net 30</p>', $fresh);
    }
}
