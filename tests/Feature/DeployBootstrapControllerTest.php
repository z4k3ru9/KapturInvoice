<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers App\Http\Controllers\DeployBootstrapController — the token-gated
 * HTTP route that stands up a brand-new production database (schema +
 * reference-data seeders + one real admin user) for a cPanel host with
 * cron but no Terminal/SSH. See config/deploy.php's own docblock.
 */
class DeployBootstrapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_route_404s_when_no_token_is_configured(): void
    {
        config(['deploy.migrate_token' => null]);

        $this->get(route('deploy.bootstrap', ['token' => 'anything']))->assertNotFound();
    }

    public function test_the_route_404s_with_a_wrong_token(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get(route('deploy.bootstrap', ['token' => 'a-wrong-token']))->assertNotFound();
    }

    public function test_the_route_404s_with_no_token_query_param_at_all(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get('/deploy/bootstrap')->assertNotFound();
    }

    public function test_a_correct_token_migrates_and_seeds_reference_data(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get(route('deploy.bootstrap', ['token' => 'the-real-token']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('companies', 2)
            ->assertJsonPath('admin_user', null);

        $this->assertDatabaseHas('companies', ['slug' => 'company-a']);
        $this->assertDatabaseHas('companies', ['slug' => 'company-b']);
        $this->assertGreaterThan(0, Currency::query()->count());
        $this->assertGreaterThan(0, Country::query()->count());
    }

    public function test_it_never_creates_the_dev_seed_test_user(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get(route('deploy.bootstrap', ['token' => 'the-real-token']))->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_it_creates_a_real_admin_user_and_attaches_them_as_owner_when_configured(): void
    {
        config([
            'deploy.migrate_token' => 'the-real-token',
            'deploy.admin_name' => 'Real Owner',
            'deploy.admin_email' => 'owner@example.com',
            'deploy.admin_password' => 'a-strong-password',
        ]);

        $this->get(route('deploy.bootstrap', ['token' => 'the-real-token']))
            ->assertOk()
            ->assertJsonPath('admin_user', 'owner@example.com');

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));

        $companies = Company::all();
        $this->assertCount(2, $companies);
        foreach ($companies as $company) {
            $this->assertSame('owner', $user->companies()->whereKey($company->id)->first()->pivot->role);
        }
    }

    public function test_it_is_safe_to_run_twice_in_a_row(): void
    {
        config([
            'deploy.migrate_token' => 'the-real-token',
            'deploy.admin_email' => 'owner@example.com',
            'deploy.admin_password' => 'a-strong-password',
        ]);

        $this->get(route('deploy.bootstrap', ['token' => 'the-real-token']))->assertOk();
        $this->get(route('deploy.bootstrap', ['token' => 'the-real-token']))->assertOk();

        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
        $this->assertSame(2, Company::count());
    }
}
