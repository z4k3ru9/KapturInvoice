<?php

namespace App\Livewire;

use App\Models\UserInvitation;
use App\Services\CompanyMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The page an internal-user invitation link (App\Mail\UserInvitationMail)
 * resolves to — the recipient sets their own local name/password, per
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §1 ("recipients set a local
 * password", no SSO/social login at launch). Not behind
 * ResolveCompanyFromDomain: the company is already fixed by the
 * invitation's token, not by which domain this is opened on.
 */
#[Layout('layouts.public')]
class AcceptInvitation extends Component
{
    public UserInvitation $invitation;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accepted = false;

    public function mount(string $token): void
    {
        $this->invitation = UserInvitation::query()->where('token', $token)->firstOrFail();
    }

    public function accept(CompanyMembershipService $memberships): void
    {
        if (! $this->invitation->isUsable()) {
            $this->addError('invitation', 'This invitation has expired or was already used.');

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $memberships->accept($this->invitation, $this->name, $this->password);

        $this->accepted = true;
    }

    public function render(): View
    {
        return view('livewire.accept-invitation');
    }
}
