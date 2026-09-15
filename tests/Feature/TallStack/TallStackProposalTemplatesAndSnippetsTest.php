<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackProposalSnippets;
use App\Livewire\TallStackProposalTemplates;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProposalSnippet;
use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Proposal Templates and Proposal Snippets library
 * (App\Livewire\TallStackProposalTemplates / App\Livewire\TallStackProposalSnippets)
 * — see App\Livewire\TallStackProposalTemplates's own docblock for the
 * established pattern this follows (App\Livewire\TallStackPaymentGateways'
 * test is the closest sibling shape: a single-component register + modal
 * editor over a model with no dedicated Policy, gated only by the generic
 * company-role Gate::before hook).
 */
class TallStackProposalTemplatesAndSnippetsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->owner, ['role' => 'owner']);

        $this->actingAs($this->owner);
    }

    // --- Templates -----------------------------------------------------

    public function test_templates_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }

    public function test_templates_list_shows_only_templates_belonging_to_the_current_company(): void
    {
        ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Cloud Migration SOW',
            'html' => '<h1>Cloud Migration</h1>',
            'css' => 'h1 { color: red; }',
        ]);

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'currency_code' => 'USD']);
        ProposalTemplate::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Template',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->assertSee('Cloud Migration SOW')
            ->assertDontSee('Other Company Template');
    }

    public function test_templates_empty_state_is_shown_when_none_exist(): void
    {
        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->assertSee('No proposal templates yet');
    }

    public function test_owner_can_create_a_proposal_template(): void
    {
        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('create')
            ->set('name', 'Managed Network Support')
            ->set('html', '<h1>Support Agreement</h1>')
            ->set('css', 'h1 { color: blue; }')
            ->call('save')
            ->assertSee('Managed Network Support');

        $template = ProposalTemplate::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('Managed Network Support', $template->name);
        $this->assertSame('<h1>Support Agreement</h1>', $template->html);
        $this->assertSame('h1 { color: blue; }', $template->css);
    }

    public function test_creating_a_template_requires_a_name(): void
    {
        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(0, ProposalTemplate::where('company_id', $this->company->id)->count());
    }

    public function test_edit_populates_the_form_and_save_updates_the_template(): void
    {
        $template = ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Original name',
            'html' => '<p>old</p>',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('edit', $template->id)
            ->assertSet('name', 'Original name')
            ->set('name', 'Renamed template')
            ->call('save');

        $this->assertSame('Renamed template', $template->fresh()->name);
    }

    public function test_duplicate_creates_an_independent_copy(): void
    {
        $template = ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Turnkey Installation',
            'html' => '<p>Body</p>',
            'css' => 'p { margin: 0; }',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('duplicate', $template->id);

        $this->assertSame(2, ProposalTemplate::where('company_id', $this->company->id)->count());
        $copy = ProposalTemplate::where('company_id', $this->company->id)->where('id', '!=', $template->id)->firstOrFail();
        $this->assertSame('Turnkey Installation (copy)', $copy->name);
        $this->assertSame($template->html, $copy->html);
    }

    public function test_delete_removes_the_template(): void
    {
        $template = ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'To be deleted',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('delete', $template->id);

        $this->assertDatabaseMissing('proposal_templates', ['id' => $template->id]);
    }

    public function test_editing_a_template_from_another_company_is_silently_refused(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-2', 'currency_code' => 'USD']);
        $foreignTemplate = ProposalTemplate::create([
            'company_id' => $otherCompany->id,
            'name' => 'Not yours',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('edit', $foreignTemplate->id)
            ->assertSet('editingId', null)
            ->assertSet('name', null);
    }

    public function test_deleting_a_template_from_another_company_is_silently_refused(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-3', 'currency_code' => 'USD']);
        $foreignTemplate = ProposalTemplate::create([
            'company_id' => $otherCompany->id,
            'name' => 'Not yours',
        ]);

        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('delete', $foreignTemplate->id);

        $this->assertDatabaseHas('proposal_templates', ['id' => $foreignTemplate->id]);
    }

    /** The live preview pane renders the same html/css the form is currently holding. */
    public function test_the_editor_renders_a_live_preview_of_the_current_html_and_css(): void
    {
        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->call('create')
            ->set('html', '<h1>Preview Marker Text</h1>')
            ->set('css', 'h1 { color: teal; }')
            ->assertSee('Preview Marker Text');
    }

    public function test_an_auditor_can_view_the_page_but_cannot_create_a_template(): void
    {
        $auditor = User::factory()->create();
        $this->company->users()->attach($auditor, ['role' => 'auditor']);
        $this->actingAs($auditor);

        // Auditor is excluded from CompanyRole::mutatingRoles(), so the
        // generic company-role Gate::before hook denies "create" outright
        // — same gate every other BelongsToCompany model already relies
        // on (see App\Livewire\TallStackPaymentGateways's own docblock).
        Livewire::test(TallStackProposalTemplates::class, ['company' => $this->company])
            ->assertOk()
            ->call('create')
            ->assertForbidden();

        $this->assertSame(0, ProposalTemplate::where('company_id', $this->company->id)->count());
    }

    // --- Snippets --------------------------------------------------------

    public function test_snippets_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-snip', 'currency_code' => 'USD']);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }

    public function test_snippets_list_shows_only_snippets_belonging_to_the_current_company(): void
    {
        ProposalSnippet::create([
            'company_id' => $this->company->id,
            'name' => 'Camera Kit Clause',
            'html' => '<p>Camera kit</p>',
        ]);

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-snip', 'currency_code' => 'USD']);
        ProposalSnippet::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Snippet',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->assertSee('Camera Kit Clause')
            ->assertDontSee('Other Company Snippet');
    }

    public function test_snippets_empty_state_is_shown_when_none_exist(): void
    {
        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->assertSee('No snippets yet');
    }

    public function test_a_product_linked_snippet_shows_the_linked_badge(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'HikVision Dome Camera',
            'unit_cost' => 1_500_000,
        ]);

        ProposalSnippet::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name' => 'Dome camera snippet',
            'html' => '<p>Dome camera</p>',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->assertSee('Dome camera snippet')
            ->assertSee('Linked to product');
    }

    public function test_a_manually_built_snippet_has_no_linked_badge(): void
    {
        ProposalSnippet::create([
            'company_id' => $this->company->id,
            'name' => 'Hand-built snippet',
            'html' => '<p>Hand built</p>',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->assertSee('Hand-built snippet')
            ->assertDontSee('Linked to product');
    }

    public function test_owner_can_create_a_snippet(): void
    {
        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->call('create')
            ->set('name', 'Warranty Clause')
            ->set('html', '<p>12-month warranty on parts and labor.</p>')
            ->call('save')
            ->assertSee('Warranty Clause');

        $snippet = ProposalSnippet::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('Warranty Clause', $snippet->name);
        $this->assertNull($snippet->product_id);
    }

    public function test_creating_a_snippet_requires_a_name(): void
    {
        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->call('create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(0, ProposalSnippet::where('company_id', $this->company->id)->count());
    }

    public function test_edit_populates_the_form_and_save_updates_the_snippet_without_touching_its_product_link(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'HikLook NVR',
            'unit_cost' => 3_000_000,
        ]);

        $snippet = ProposalSnippet::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name' => 'NVR snippet',
            'html' => '<p>old</p>',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->call('edit', $snippet->id)
            ->assertSet('name', 'NVR snippet')
            ->set('html', '<p>new</p>')
            ->call('save');

        $snippet->refresh();
        $this->assertSame('<p>new</p>', $snippet->html);
        $this->assertSame($product->id, $snippet->product_id);
    }

    public function test_delete_removes_the_snippet(): void
    {
        $snippet = ProposalSnippet::create([
            'company_id' => $this->company->id,
            'name' => 'To be deleted',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->call('delete', $snippet->id);

        $this->assertDatabaseMissing('proposal_snippets', ['id' => $snippet->id]);
    }

    public function test_editing_a_snippet_from_another_company_is_silently_refused(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-snip-2', 'currency_code' => 'USD']);
        $foreignSnippet = ProposalSnippet::create([
            'company_id' => $otherCompany->id,
            'name' => 'Not yours',
        ]);

        Livewire::test(TallStackProposalSnippets::class, ['company' => $this->company])
            ->call('edit', $foreignSnippet->id)
            ->assertSet('editingId', null)
            ->assertSet('name', null);
    }
}
