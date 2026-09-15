<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Autosave is draft-only, batched, debounced, conflict-aware, and never
 * performs a financial action... Add server version or equivalent
 * optimistic concurrency protection... Preserve local values after
 * failed save and expose explicit retry." Exercises
 * App\Filament\Concerns\AutosavesDraft via App\Filament\Resources\
 * Invoices\Pages\EditInvoice, the reference implementation.
 */
class InvoiceAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function draftInvoice(): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-DRAFT',
            'currency_code' => 'USD',
        ]);
    }

    public function test_autosave_persists_a_draft_field_and_advances_the_version(): void
    {
        $invoice = $this->draftInvoice();

        // Setting the field alone is enough — InvoiceForm wires
        // `->live(debounce: '1750ms')->afterStateUpdated(...)` on every
        // autosave-eligible field, so this exercises the real end-to-end
        // wiring (field change -> afterStateUpdated -> autosaveDraft()),
        // not just a raw method call.
        $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->set('data.terms', 'Net 30');

        $component->assertSet('autosaveStatus', 'saved');

        $fresh = $invoice->fresh();
        $this->assertSame('Net 30', $fresh->terms);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_a_stale_autosave_is_rejected_as_a_conflict_not_silently_merged(): void
    {
        $invoice = $this->draftInvoice();

        // Simulate another tab/user autosaving first, advancing the
        // server's draft_version past what this page still knows about.
        $invoice->forceFill(['terms' => 'Someone else typed this', 'draft_version' => 1])->save();

        $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()]);
        // The component mounted and cached draft_version = 0 *before* the
        // line above ran in a real scenario; here we force that stale
        // state directly for a deterministic test.
        $component->set('autosaveKnownVersion', 0)
            ->set('data.terms', 'My own edit')
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
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['terms' => 'Server value', 'draft_version' => 1])->save();

        $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->set('autosaveKnownVersion', 0)
            ->set('data.terms', 'My local edit')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict')
            ->call('discardAutosaveConflict');

        $component->assertSet('autosaveStatus', 'idle')
            ->assertSet('data.terms', 'Server value')
            ->assertSet('autosaveKnownVersion', 1);
    }

    public function test_overwriting_a_conflict_forces_the_local_edit_through(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['terms' => 'Server value', 'draft_version' => 1])->save();

        $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->set('autosaveKnownVersion', 0)
            ->set('data.terms', 'My local edit, keep it')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict')
            ->call('overwriteAutosaveConflict');

        $component->assertSet('autosaveStatus', 'saved');
        $this->assertSame('My local edit, keep it', $invoice->fresh()->terms);
        $this->assertSame(2, $invoice->fresh()->draft_version);
    }

    public function test_a_failed_autosave_preserves_local_values_for_retry(): void
    {
        $invoice = $this->draftInvoice();

        // Simulate a genuine save-time failure (schema drift, DB outage,
        // a dropped column) with a real, self-contained schema change
        // rather than an Eloquent event listener — saveQuietly() runs
        // via Model::withoutEvents(), so a `saving` listener would never
        // even fire and this must instead be a real failure the
        // underlying UPDATE statement hits. Restored in `finally`.
        Schema::table('invoices', function ($table) {
            $table->renameColumn('terms', 'terms_temporarily_renamed');
        });

        try {
            $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
                ->set('data.terms', 'Typed while offline')
                ->call('autosaveDraft');

            $component->assertSet('autosaveStatus', 'failed')
                ->assertSet('data.terms', 'Typed while offline');

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
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['status' => InvoiceStatus::Issued])->save();

        $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->set('data.terms', 'Trying to edit an issued invoice')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'idle');
        $this->assertNotSame('Trying to edit an issued invoice', $invoice->fresh()->terms);
    }

    public function test_autosave_never_touches_status_or_derived_totals(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['total' => 500, 'balance' => 500])->save();

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->set('data.terms', 'A perfectly normal draft edit')
            ->call('autosaveDraft');

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Draft, $fresh->status);
        $this->assertSame('500.00', $fresh->total);
        $this->assertSame('500.00', $fresh->balance);
    }
}
