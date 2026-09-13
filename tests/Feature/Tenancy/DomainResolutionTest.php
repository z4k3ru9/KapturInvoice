<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Middleware\ResolveCompanyFromDomain must reject a disabled
 * company exactly like an unknown host — see
 * docs/rebuild/specs/01-company-foundation/Specs.md "Reject unknown,
 * disabled, or mismatched company contexts." Basic domain-match/local-env
 * fallback behavior is already covered by tests/Feature/HomePageTest.php.
 */
class DomainResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_disabled_companys_domain_is_not_resolvable(): void
    {
        Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'domain' => 'disabled.test', 'is_active' => false]);

        $this->get('http://disabled.test/')->assertNotFound();
    }

    public function test_a_disabled_company_is_excluded_from_the_local_env_fallback(): void
    {
        Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'is_active' => false]);
        Company::create(['name' => 'Active Co', 'slug' => 'active-co']);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('Active Co')
            ->assertDontSee('Disabled Co');
    }

    public function test_when_only_a_disabled_company_exists_the_fallback_finds_nothing(): void
    {
        Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'is_active' => false]);

        $this->get('http://localhost/')->assertNotFound();
    }
}
