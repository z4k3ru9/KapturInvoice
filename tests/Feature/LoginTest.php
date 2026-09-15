<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Livewire\Login — the app's only non-Filament, standalone
 * login page, and the App\Http\Controllers\LogoutController it pairs
 * with. This is the entry point every middleware('auth') route across
 * the TALL-stack/portal/register-company side of the app relies on
 * (Illuminate\Auth\Middleware\Authenticate::redirectTo() resolves
 * route('login')) — before this existed that resolution threw
 * RouteNotFoundException instead of redirecting.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_a_guest(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_valid_credentials_log_in_and_redirect_to_the_users_company_dashboard(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('tallstack.dashboard', $company));

        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::id() === $user->id);
    }

    public function test_invalid_credentials_show_an_authentication_error_and_do_not_log_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['authentication'])
            ->assertNoRedirect();

        $this->assertFalse(Auth::check());
    }

    public function test_unknown_email_shows_the_same_generic_credentials_error(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nobody@example.com')
            ->set('password', 'whatever')
            ->call('login')
            ->assertHasErrors(['authentication'])
            ->assertNoRedirect();

        $this->assertFalse(Auth::check());
    }

    public function test_email_and_password_are_required(): void
    {
        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_a_user_with_no_company_is_redirected_to_register_company_after_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('tallstack.register-company'));
    }

    public function test_a_super_admin_is_redirected_to_the_first_active_company_even_without_membership(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password'), 'is_super_admin' => true]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('tallstack.dashboard', $company));
    }

    public function test_an_already_authenticated_visitor_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);

        Livewire::test(Login::class)
            ->assertRedirect(route('tallstack.dashboard', $company));
    }

    public function test_a_middleware_auth_route_redirects_a_guest_to_login_instead_of_erroring(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $this->get(route('tallstack.dashboard', $company))
            ->assertRedirect(route('login'));
    }

    public function test_a_guest_bounced_to_login_returns_to_the_originally_requested_page_after_logging_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        // Triggers Illuminate\Auth\Middleware\Authenticate's guest
        // redirect, which stores the original URL under the
        // 'url.intended' session key.
        $this->get(route('tallstack.dashboard', $company))->assertRedirect(route('login'));

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('tallstack.dashboard', $company));
    }

    public function test_logout_logs_out_and_redirects_to_login(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertTrue(Auth::check());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
