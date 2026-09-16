<?php

namespace App\Livewire;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * A user's own passkeys — deliberately NOT company-scoped (a passkey
 * authenticates the person, not a tenant membership; App\Models\User's
 * own `passkeys()` relation, from Laravel\Passkeys\PasskeyAuthenticatable,
 * has no company_id at all). Still routed under `/tall/{company:slug}/...`
 * and rendered inside the shared shell purely because that's the only
 * place a user reaches this from (their own avatar menu, which only ever
 * renders within a resolved company) — the `{company:slug}` in the URL
 * is not otherwise meaningful to this page.
 *
 * The actual WebAuthn registration ceremony is entirely client-side
 * (window.Passkeys.register(), resources/js/app.js) against the
 * package's own `/user/passkeys` routes — this component never talks to
 * the authenticator itself, it only re-queries the resulting rows
 * (`refreshPasskeys()`, called from the browser once registration
 * succeeds) and handles deletion directly against the same table this
 * app already owns, no HTTP round trip to the package's own DELETE route
 * needed for that half.
 */
#[Layout('components.tallstack.app')]
class TallStackAccountPasskeys extends Component
{
    use Interactions;

    public Company $company;

    public function mount(Company $company): void
    {
        abort_unless(Auth::user()->canAccessTenant($company), 403);

        $this->company = $company;
    }

    /** Called from the browser (wire:click, no args) once Passkeys.register() resolves — re-pulls the just-created row. */
    public function refreshPasskeys(): void
    {
        // No-op body: render() below always re-queries fresh from the
        // database, so simply being called (any Livewire round trip)
        // is enough to show the newly registered passkey without a
        // full page reload.
    }

    public function delete(int $id): void
    {
        $passkey = Auth::user()->passkeys()->whereKey($id)->first();

        if (! $passkey) {
            return;
        }

        $passkey->delete();

        $this->toast()->success('Passkey removed.')->send();
    }

    public function render(): View
    {
        $passkeys = Auth::user()->passkeys()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'created_at' => $passkey->created_at?->format('d M Y'),
                'last_used_at' => $passkey->last_used_at?->diffForHumans() ?? 'Never used',
            ]);

        return view('livewire.tallstack-account-passkeys', ['passkeys' => $passkeys])->layoutData([
            'company' => $this->company,
            'active' => null,
            'title' => 'Passkeys',
        ]);
    }
}
