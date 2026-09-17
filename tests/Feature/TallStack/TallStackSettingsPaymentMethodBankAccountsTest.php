<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSettingsPaymentMethod;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bank accounts (App\Models\CompanyBankAccount) managed from the Company &
 * Taxes settings page, printed as a "Payment Method" section on the
 * Invoice PDF (see tests/Feature/PdfExportTest.php for the rendering
 * coverage). A company may have more than one — the CRUD here is
 * deliberately generic (add/edit/delete), not a fixed two-slot form.
 */
class TallStackSettingsPaymentMethodBankAccountsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_a_bank_account_can_be_created(): void
    {
        Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company])
            ->call('openCreateBankAccountModal')
            ->set('ba_bank_name', 'Bank Central Asia')
            ->set('ba_account_name', 'PT Acme Indonesia')
            ->set('ba_account_number', '1234567890')
            ->set('ba_branch', 'Surabaya Darmo')
            ->call('saveBankAccount')
            ->assertSet('showBankAccountModal', false);

        $this->assertDatabaseHas('company_bank_accounts', [
            'company_id' => $this->company->id,
            'bank_name' => 'Bank Central Asia',
            'account_number' => '1234567890',
        ]);
    }

    public function test_a_company_can_have_more_than_one_bank_account(): void
    {
        $component = Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company]);

        $component->call('openCreateBankAccountModal')
            ->set('ba_bank_name', 'Bank Central Asia')
            ->set('ba_account_name', 'PT Acme Indonesia')
            ->set('ba_account_number', '1111111111')
            ->call('saveBankAccount');

        $component->call('openCreateBankAccountModal')
            ->set('ba_bank_name', 'Bank Mandiri')
            ->set('ba_account_name', 'PT Acme Indonesia')
            ->set('ba_account_number', '2222222222')
            ->call('saveBankAccount');

        $this->assertSame(2, $this->company->bankAccounts()->count());
    }

    public function test_a_bank_account_can_be_edited(): void
    {
        $account = CompanyBankAccount::create([
            'company_id' => $this->company->id,
            'bank_name' => 'Bank Central Asia',
            'account_name' => 'PT Acme Indonesia',
            'account_number' => '1234567890',
        ]);

        Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company])
            ->call('openEditBankAccountModal', $account->id)
            ->assertSet('ba_bank_name', 'Bank Central Asia')
            ->set('ba_account_number', '9999999999')
            ->call('saveBankAccount');

        $this->assertSame('9999999999', $account->fresh()->account_number);
    }

    public function test_a_bank_account_can_be_deleted(): void
    {
        $account = CompanyBankAccount::create([
            'company_id' => $this->company->id,
            'bank_name' => 'Bank Central Asia',
            'account_name' => 'PT Acme Indonesia',
            'account_number' => '1234567890',
        ]);

        Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company])
            ->call('deleteBankAccount', $account->id);

        $this->assertSoftDeleted('company_bank_accounts', ['id' => $account->id]);
    }

    public function test_a_bank_account_cannot_be_edited_or_deleted_from_a_different_company(): void
    {
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'currency_code' => 'USD']);
        $account = CompanyBankAccount::create([
            'company_id' => $other->id,
            'bank_name' => 'Bank Central Asia',
            'account_name' => 'Other Co',
            'account_number' => '1234567890',
        ]);

        Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company])
            ->call('openEditBankAccountModal', $account->id)
            ->assertSet('showBankAccountModal', false)
            ->call('deleteBankAccount', $account->id);

        $this->assertDatabaseHas('company_bank_accounts', ['id' => $account->id, 'deleted_at' => null]);
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(TallStackSettingsPaymentMethod::class, ['company' => $this->company])
            ->call('openCreateBankAccountModal')
            ->call('saveBankAccount')
            ->assertHasErrors(['ba_bank_name', 'ba_account_name', 'ba_account_number']);
    }
}
