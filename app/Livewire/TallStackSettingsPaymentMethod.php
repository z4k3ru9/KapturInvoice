<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Payment Method" settings screen — a company's own bank
 * accounts, printed as a "Payment Method" section on the Invoice PDF. A
 * company may have more than one (e.g. two different banks). Split out
 * of the former "Company & Taxes" mega-page into its own tab (2026-09-17
 * Settings reorganization, see memory.md) — deliberately kept separate
 * from Documents & Numbering rather than merged in: receiving payment is
 * a different concern from how documents get numbered, per an explicit
 * Owner decision during that reorganization.
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never" gate
 * every other TALL-stack Settings page uses, re-checked in mount() and
 * again in every action.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsPaymentMethod extends Component
{
    use Interactions;

    public Company $company;

    public bool $showBankAccountModal = false;

    public ?int $editingBankAccountId = null;

    public ?string $ba_bank_name = null;

    public ?string $ba_account_name = null;

    public ?string $ba_account_number = null;

    public ?string $ba_branch = null;

    public ?string $ba_swift_code = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);
    }

    public function openCreateBankAccountModal(): void
    {
        $this->authorize('viewSettings', $this->company);

        $this->resetBankAccountForm();
        $this->showBankAccountModal = true;
    }

    public function openEditBankAccountModal(int $id): void
    {
        $account = $this->scopedBankAccount($id);

        if (! $account) {
            return;
        }

        $this->authorize('viewSettings', $this->company);

        $this->editingBankAccountId = $account->id;
        $this->ba_bank_name = $account->bank_name;
        $this->ba_account_name = $account->account_name;
        $this->ba_account_number = $account->account_number;
        $this->ba_branch = $account->branch;
        $this->ba_swift_code = $account->swift_code;
        $this->showBankAccountModal = true;
    }

    public function saveBankAccount(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'ba_bank_name' => ['required', 'string', 'max:255'],
            'ba_account_name' => ['required', 'string', 'max:255'],
            'ba_account_number' => ['required', 'string', 'max:255'],
            'ba_branch' => ['nullable', 'string', 'max:255'],
            'ba_swift_code' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'bank_name' => $data['ba_bank_name'],
            'account_name' => $data['ba_account_name'],
            'account_number' => $data['ba_account_number'],
            'branch' => $data['ba_branch'],
            'swift_code' => $data['ba_swift_code'],
        ];

        if ($this->editingBankAccountId) {
            $account = $this->scopedBankAccount($this->editingBankAccountId);

            if (! $account) {
                return;
            }

            $account->update($payload);
        } else {
            $payload['company_id'] = $this->company->id;
            $payload['sort_order'] = $this->company->bankAccounts()->count();
            CompanyBankAccount::create($payload);
        }

        $this->showBankAccountModal = false;
        $this->resetBankAccountForm();
        $this->toast()->success('Bank account saved.')->send();
    }

    public function deleteBankAccount(int $id): void
    {
        $account = $this->scopedBankAccount($id);

        if (! $account) {
            return;
        }

        $this->authorize('viewSettings', $this->company);

        $account->delete();

        $this->toast()->success('Bank account removed.')->send();
    }

    /** Never trust a bare `CompanyBankAccount::find()` — always re-check company ownership. */
    private function scopedBankAccount(?int $id): ?CompanyBankAccount
    {
        if (! $id) {
            return null;
        }

        $account = CompanyBankAccount::find($id);

        if (! $account || $account->company_id !== $this->company->id) {
            return null;
        }

        return $account;
    }

    private function resetBankAccountForm(): void
    {
        $this->editingBankAccountId = null;
        $this->ba_bank_name = null;
        $this->ba_account_name = null;
        $this->ba_account_number = null;
        $this->ba_branch = null;
        $this->ba_swift_code = null;
    }

    public function render(): View
    {
        $bankAccounts = $this->company->bankAccounts->map(fn (CompanyBankAccount $account) => [
            'id' => $account->id,
            'name' => "{$account->bank_name} — {$account->account_number}",
            'caption' => $account->branch
                ? "{$account->account_name} · {$account->branch}"
                : $account->account_name,
        ]);

        return view('livewire.tallstack-settings-payment-method', [
            'bankAccounts' => $bankAccounts,
        ])
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Payment Method',
            ]);
    }
}
