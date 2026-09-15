<?php

namespace App\Livewire;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The app's only login page — routed at `/login` (named `login`, matching
 * Laravel's own guest-redirect convention: every `middleware('auth')`
 * route across the TALL-stack side of this app, the portal, and
 * `/register-company` relies on `route('login')` resolving, via
 * Illuminate\Auth\Middleware\Authenticate::redirectTo()). The Filament
 * admin panel this once ran alongside (`/admin/login`) has since been
 * fully removed — see the note near the top of CLAUDE.md — so this is
 * now the only authentication entry point in the app.
 *
 * Authenticates against the plain `web` guard / `App\Models\User` eloquent
 * provider (config/auth.php's defaults), so a plain `Auth::attempt()` here
 * is a real, working credential check against the same user table, not a
 * parallel auth system.
 *
 * Post-login redirect mirrors App\Livewire\TallStackRegisterCompany's own
 * "existing company" resolution exactly (first active company — a super
 * admin's first active Company row, otherwise the first active
 * `company_user` membership) so both pages agree on "which company" for a
 * multi-company user. A stored `session('url.intended')` (set by
 * `redirect()->guest()` when an unauthenticated visit to a
 * `middleware('auth')` route triggered the bounce here) takes priority
 * over that default landing, so a visitor sent here from a specific
 * protected page returns to it after logging in.
 */
#[Layout('layouts.public')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        // Already signed in — nothing to do here, send them where a
        // fresh login would have.
        if (Auth::check()) {
            $this->redirectToDestination();
        }
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        // Standard Laravel login throttling (the same
        // RateLimiter::tooManyAttempts/hit/clear shape Laravel's own
        // scaffolding — Breeze/Fortify — has always used for this exact
        // form; this app has neither installed, so it's reproduced
        // directly rather than inventing a different scheme).
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            // Keyed 'authentication', not 'email' — keeps this distinct
            // from the field-level required/format errors 'email' already
            // carries, so the banner below never duplicates a per-field
            // message under the input.
            throw ValidationException::withMessages([
                'authentication' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'authentication' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // The `session()` helper (bound to the container's session
        // manager), not `request()->session()` — Livewire component
        // tests (Livewire::test(), used by tests/Feature/LoginTest.php)
        // don't attach a session store to the underlying test Request the
        // way a full HTTP request through StartSession middleware does,
        // so the latter throws "Session store not set on request" there
        // even though a real browser request works fine either way.
        session()->regenerate();

        $this->redirectToDestination();
    }

    private function redirectToDestination(): void
    {
        $company = $this->destinationCompany();

        $fallback = $company
            ? route('tallstack.dashboard', $company)
            : route('tallstack.register-company');

        // redirect()->intended() both resolves the stored
        // `url.intended` session value (set by Laravel's own
        // redirect()->guest() when a middleware('auth') route bounced an
        // unauthenticated visitor here) and forgets it — same call
        // whether or not one was actually stored.
        $this->redirect(redirect()->intended($fallback)->getTargetUrl(), navigate: false);
    }

    /**
     * Mirrors App\Livewire\TallStackRegisterCompany::existingCompany()
     * exactly, so a logged-in user always lands on the same company both
     * pages would pick.
     */
    private function destinationCompany(): ?Company
    {
        $user = Auth::user();

        return $user->is_super_admin
            ? Company::query()->active()->first()
            : $user->companies()->active()->wherePivot('is_active', true)->first();
    }

    public function render(): View
    {
        return view('livewire.login');
    }
}
