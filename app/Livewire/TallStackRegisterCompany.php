<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * TallStackUI-native replacement for the equivalent pre-TallStackUI
 * Filament company-registration page (Filament's `RegisterTenant` page) —
 * the only path that creates a new
 * `Company` row and attaches the creating user to it as `owner`. It calls
 * the exact same fillable fields and the exact same `company_user` attach
 * call (`['role' => 'owner']`, `is_active` left to its DB default of
 * `true`) that RegisterCompany's `handleRegistration()` used. See
 * docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md.
 *
 * The Filament admin panel (and its own `->tenantRegistration()`
 * middleware) has since been fully removed — see the note near the top of
 * CLAUDE.md — so this page, reachable at `/register-company`, is now the
 * app's real entry point for an authenticated user with no company yet.
 * `App\Livewire\Login::redirectToDestination()` sends a company-less user
 * here directly after a successful login.
 */
#[Layout('layouts.public')]
class TallStackRegisterCompany extends Component
{
    public string $name = '';

    public string $slug = '';

    public ?string $domain = null;

    public string $currency_code = 'USD';

    public ?string $primary_color = null;

    /**
     * Mirrors Filament's own zero-tenant/has-tenant framing: a user who
     * already belongs to (or, as a super admin, can already reach) at
     * least one active company has nothing to register here — send them
     * straight to that company's dashboard instead of showing this form.
     */
    public function mount(): void
    {
        $existing = $this->existingCompany();

        if ($existing) {
            $this->redirectRoute('tallstack.dashboard', $existing, navigate: false);
        }
    }

    /**
     * Mirrors RegisterCompany's `TextInput::make('name')->live(onBlur: true)
     * ->afterStateUpdated(...)` — every update to the name field
     * (re)derives the slug from it, the same unconditional overwrite
     * behavior the Filament form has (not just a one-time default).
     * `wire:model.blur` (the literal onBlur equivalent) does not fire an
     * update in this app's Livewire 4 + TallStackUI `<x-input>` setup — no
     * other TALL-stack page in this codebase uses that modifier, they all
     * use `wire:model.live[.debounce]` — so the view uses
     * `wire:model.live.debounce.500ms` instead, the same proven pattern
     * every other text field's live behavior in this app already uses.
     */
    public function updatedName(string $value): void
    {
        $this->slug = Str::slug($value);
    }

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('companies', 'slug')],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('companies', 'domain')],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'primary_color' => ['nullable', 'string', 'max:20'],
        ]);

        $company = Company::create($data);

        $company->users()->attach(Auth::id(), ['role' => 'owner']);

        $this->redirectRoute('tallstack.dashboard', $company, navigate: false);
    }

    public function render(): View
    {
        return view('livewire.tallstack-register-company', [
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
        ]);
    }

    private function existingCompany(): ?Company
    {
        $user = Auth::user();

        return $user->is_super_admin
            ? Company::query()->active()->first()
            : $user->companies()->active()->wherePivot('is_active', true)->first();
    }
}
