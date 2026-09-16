<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\SignDeliveryOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public "sign my delivery order" page `delivery_orders.portal_key`
 * resolves to — mirrors ViewInvoicePortalTest's coverage shape for the
 * same drawn-signature capability extended to Delivery Orders.
 */
class SignDeliveryOrderPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveryOrder(Company $company): DeliveryOrder
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-0001',
            'status' => 'accepted',
        ]);
        $job = SalesOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'JOB-0001',
        ]);
        $deliveryOrder = DeliveryOrder::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'DO-0001',
            'delivery_date' => '2026-09-10',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'description' => 'Router unit',
            'quantity_delivered' => 3,
        ]);

        return $deliveryOrder;
    }

    public function test_portal_page_shows_the_delivery_order_for_the_domain_matched_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);

        $this->assertNotNull($deliveryOrder->portal_key);

        $this->get("http://acme.test/portal/delivery-orders/{$deliveryOrder->portal_key}")
            ->assertOk()
            ->assertSee('DO-0001')
            ->assertSee('Router unit');
    }

    public function test_portal_page_404s_when_the_delivery_order_belongs_to_a_different_company_than_the_resolved_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $deliveryOrder = $this->makeDeliveryOrder($owner);

        $this->get("http://{$other->domain}/portal/delivery-orders/{$deliveryOrder->portal_key}")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertDontSee($deliveryOrder->number);
    }

    public function test_an_unknown_portal_key_renders_the_calm_unavailable_page_instead_of_a_bare_404(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);

        $this->get("http://{$company->domain}/portal/delivery-orders/does-not-exist")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertSee('Acme');
    }

    public function test_signing_records_the_drawn_signature_name_and_timestamp(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);
        $this->app->instance('currentCompany', $company);

        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        Livewire::test(SignDeliveryOrder::class, ['deliveryOrder' => $deliveryOrder])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', $signature)
            ->call('sign')
            ->assertHasNoErrors()
            ->assertSet('justSigned', true);

        $deliveryOrder->refresh();
        $this->assertSame($signature, $deliveryOrder->signature);
        $this->assertSame('Jane Doe', $deliveryOrder->signed_by_name);
        $this->assertNotNull($deliveryOrder->signed_at);
    }

    public function test_signing_without_a_drawn_signature_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignDeliveryOrder::class, ['deliveryOrder' => $deliveryOrder])
            ->set('signerName', 'Jane Doe')
            ->call('sign')
            ->assertHasErrors(['capturedSignature' => 'required'])
            ->assertSet('justSigned', false);

        $deliveryOrder->refresh();
        $this->assertNull($deliveryOrder->signature);
        $this->assertNull($deliveryOrder->signed_at);
    }

    public function test_signing_with_a_non_image_value_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignDeliveryOrder::class, ['deliveryOrder' => $deliveryOrder])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', 'not an image')
            ->call('sign')
            ->assertHasErrors(['capturedSignature' => 'starts_with'])
            ->assertSet('justSigned', false);

        $deliveryOrder->refresh();
        $this->assertNull($deliveryOrder->signature);
    }

    /**
     * See ViewInvoicePortalTest::test_signing_with_an_oversized_signature_payload_is_rejected()
     * — same gap, same fix, applied to this sibling portal endpoint.
     */
    public function test_signing_with_an_oversized_signature_payload_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);
        $this->app->instance('currentCompany', $company);

        $oversized = 'data:image/png;base64,'.str_repeat('A', 2_000_001);

        Livewire::test(SignDeliveryOrder::class, ['deliveryOrder' => $deliveryOrder])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', $oversized)
            ->call('sign')
            ->assertHasErrors(['capturedSignature' => 'max'])
            ->assertSet('justSigned', false);

        $deliveryOrder->refresh();
        $this->assertNull($deliveryOrder->signature);
    }

    public function test_a_signed_delivery_order_renders_the_signature_image(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $deliveryOrder = $this->makeDeliveryOrder($company);
        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');
        $deliveryOrder->forceFill(['signature' => $signature, 'signed_by_name' => 'Jane Doe', 'signed_at' => now()])->save();

        $response = $this->get("http://acme.test/portal/delivery-orders/{$deliveryOrder->portal_key}")->assertOk();

        $response->assertSee('src="'.$signature.'"', false);
        $response->assertSee('Jane Doe');
    }
}
