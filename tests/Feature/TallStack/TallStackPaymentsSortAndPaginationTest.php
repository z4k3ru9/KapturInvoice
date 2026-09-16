<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackPayments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers wiring the TallStackUI <x-table>'s built-in sort/quantity onto
 * this register: sorting by the joined "client" column (clients.name,
 * joined once on the paginated query only — payments.client_id is never
 * null, so this is a plain inner join, unlike Proposals/Credits) must not
 * throw an ambiguous-column SQL error, and the quantity filter must
 * actually cap the page size.
 */
class TallStackPaymentsSortAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    private function paymentForClient(string $clientName): Payment
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);

        return Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'amount' => 100,
            'status' => 'pending',
            'payment_date' => now(),
        ]);
    }

    public function test_sorting_by_client_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        $this->paymentForClient('Zeta Client');
        $this->paymentForClient('Alpha Client');
        $this->paymentForClient('Mid Client');

        $component = Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->assertHasNoErrors();

        $names = collect($component->viewData('payments')->items())->pluck('client')->all();
        $this->assertSame(['Alpha Client', 'Mid Client', 'Zeta Client'], $names);

        $limited = Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('payments')->items());
        $this->assertSame(3, $limited->viewData('payments')->total());
    }
}
