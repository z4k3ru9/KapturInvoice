<?php

namespace Tests\Feature\Parties;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 02 required tests (docs/rebuild/specs/02-parties-and-catalog/Specs.md):
 * "Company scoping for every party/catalog resource" and "Duplicate names
 * across companies remain separate" — for Client, Vendor, and Product
 * (catalog item), the three party/catalog models this phase touches.
 * Company-scoping itself is enforced generically by
 * App\Models\Concerns\BelongsToCompany; this test proves it actually holds
 * for these three models specifically, the way docs/rebuild/specs/00-gate-0
 * onward requires every phase to prove its own slice rather than trust a
 * shared trait by inspection alone.
 */
class PartyScopingTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'currency_code' => 'USD']);
        $this->companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'currency_code' => 'USD']);

        $this->companyA->users()->attach($user, ['role' => 'owner']);
        $this->companyB->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
    }

    public function test_a_client_with_the_same_name_in_both_companies_stays_two_separate_records(): void
    {
        $clientA = Client::create(['company_id' => $this->companyA->id, 'name' => 'Same Name Client']);
        $clientB = Client::create(['company_id' => $this->companyB->id, 'name' => 'Same Name Client']);

        $this->assertNotSame($clientA->id, $clientB->id);

        Filament::setTenant($this->companyA);
        app(Tenancy::class)->set($this->companyA);
        $this->assertTrue(Client::query()->whereKey($clientA->id)->exists());
        $this->assertFalse(Client::query()->whereKey($clientB->id)->exists());

        Filament::setTenant($this->companyB);
        app(Tenancy::class)->set($this->companyB);
        $this->assertTrue(Client::query()->whereKey($clientB->id)->exists());
        $this->assertFalse(Client::query()->whereKey($clientA->id)->exists());
    }

    public function test_a_vendor_with_the_same_name_in_both_companies_stays_two_separate_records(): void
    {
        $vendorA = Vendor::create(['company_id' => $this->companyA->id, 'name' => 'Same Name Vendor']);
        $vendorB = Vendor::create(['company_id' => $this->companyB->id, 'name' => 'Same Name Vendor']);

        $this->assertNotSame($vendorA->id, $vendorB->id);

        Filament::setTenant($this->companyA);
        app(Tenancy::class)->set($this->companyA);
        $this->assertTrue(Vendor::query()->whereKey($vendorA->id)->exists());
        $this->assertFalse(Vendor::query()->whereKey($vendorB->id)->exists());

        Filament::setTenant($this->companyB);
        app(Tenancy::class)->set($this->companyB);
        $this->assertTrue(Vendor::query()->whereKey($vendorB->id)->exists());
        $this->assertFalse(Vendor::query()->whereKey($vendorA->id)->exists());
    }

    public function test_a_catalog_item_with_the_same_name_in_both_companies_stays_two_separate_records(): void
    {
        $itemA = Product::create(['company_id' => $this->companyA->id, 'name' => 'Same Name Item']);
        $itemB = Product::create(['company_id' => $this->companyB->id, 'name' => 'Same Name Item']);

        $this->assertNotSame($itemA->id, $itemB->id);

        Filament::setTenant($this->companyA);
        app(Tenancy::class)->set($this->companyA);
        $this->assertTrue(Product::query()->whereKey($itemA->id)->exists());
        $this->assertFalse(Product::query()->whereKey($itemB->id)->exists());

        Filament::setTenant($this->companyB);
        app(Tenancy::class)->set($this->companyB);
        $this->assertTrue(Product::query()->whereKey($itemB->id)->exists());
        $this->assertFalse(Product::query()->whereKey($itemA->id)->exists());
    }
}
