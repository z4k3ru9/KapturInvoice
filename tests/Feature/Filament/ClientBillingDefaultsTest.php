<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A client's default discount (ClientForm's "Billing defaults" section)
 * prefills onto new invoices for that client — closes the gap flagged as
 * "discount percentage toggle is for per invoice, not setup in client
 * data". Per-item and invoice-level discount already existed (see
 * ItemsRelationManager/InvoiceForm) and are unchanged here.
 */
class ClientBillingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    public function test_client_can_be_created_with_a_default_discount_via_modal(): void
    {
        Livewire::test(ListClients::class)
            ->callAction('create', data: [
                'name' => 'Discounted Co',
                'default_discount' => 15,
                'default_discount_is_percentage' => true,
            ])
            ->assertHasNoActionErrors();

        $client = Client::where('name', 'Discounted Co')->firstOrFail();
        $this->assertSame('15.00', (string) $client->default_discount);
        $this->assertTrue($client->default_discount_is_percentage);
    }

    public function test_selecting_a_client_on_the_invoice_form_prefills_its_default_discount(): void
    {
        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Discounted Co',
            'default_discount' => 10,
            'default_discount_is_percentage' => true,
        ]);

        Livewire::test(CreateInvoice::class, ['tenant' => $this->company])
            ->set('data.client_id', $client->id)
            ->assertSet('data.discount', '10.00')
            ->assertSet('data.discount_is_percentage', true);
    }

    public function test_client_view_page_shows_billing_defaults_and_tax_id(): void
    {
        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Discounted Co',
            'tax_number' => 'TAX-123',
            'default_discount' => 10,
            'default_discount_is_percentage' => true,
        ]);

        $this->get(ClientResource::getUrl('view', ['record' => $client], tenant: $this->company))
            ->assertOk()
            ->assertSee('TAX-123')
            ->assertSee('10.00')
            ->assertSee('Percentage');
    }
}
