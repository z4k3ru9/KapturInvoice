<?php

namespace Tests\Feature;

use App\Livewire\HomePage;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public homepage is resolved per-domain by
 * App\Http\Middleware\ResolveCompanyFromDomain — separate from, and
 * untouched by, the legacy admin admin panel's own tenancy.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_domain_matched_company(): void
    {
        Company::create(['name' => 'Match Co', 'slug' => 'match-co', 'domain' => 'match.test']);
        Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'domain' => 'other.test']);

        $this->get('http://match.test/')
            ->assertOk()
            ->assertSee('Match Co')
            ->assertDontSee('Other Co');
    }

    public function test_homepage_falls_back_to_the_first_company_in_local_env_when_no_domain_matches(): void
    {
        Company::create(['name' => 'Fallback Co', 'slug' => 'fallback-co']);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('Fallback Co');
    }

    public function test_contact_form_submission_creates_an_inquiry_for_the_resolved_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);

        // Normally bound by ResolveCompanyFromDomain; Livewire::test() mounts
        // the component directly, bypassing route middleware, so bind what
        // the middleware would have bound.
        $this->app->instance('currentCompany', $company);

        Livewire::test(HomePage::class)
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('message', 'Please reach out.')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('inquiries', [
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }
}
