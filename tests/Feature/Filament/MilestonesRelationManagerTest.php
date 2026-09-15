<?php

namespace Tests\Feature\Filament;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\MilestonesRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for a real Phase 03 finding: the milestone form's
 * "Amount is a percentage of job value" toggle let a user enter a
 * `percentage`, but nothing ever computed `amount` from it —
 * App\Actions\Sales\ApproveSalesOrder validates milestone totals against
 * `amount` alone, so a percentage-based milestone silently required the
 * user to also type the correct amount by hand. Fixed in
 * MilestonesRelationManager to derive `amount` live from
 * `percentage * sales_order.approved_value` whenever percentage mode is on.
 */
class MilestonesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithApprovedValue(float $approvedValue): SalesOrder
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => QuotationStatus::Draft,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 1,
            'unit_cost' => $approvedValue,
            'line_total' => $approvedValue,
        ]);
        $quotation->forceFill(['subtotal' => $approvedValue, 'total' => $approvedValue])->save();

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Approved);
        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);
        app(AcceptQuotation::class)->accept($quotation->fresh());

        return app(CreateSalesOrderFromQuotation::class)->create($quotation->fresh());
    }

    public function test_toggling_percentage_mode_and_entering_a_percentage_computes_the_amount(): void
    {
        $user = User::factory()->create();
        $salesOrder = $this->jobWithApprovedValue(2000);
        $salesOrder->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($salesOrder->company);
        app(Tenancy::class)->set($salesOrder->company);

        Livewire::test(MilestonesRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->mountTableAction('create')
            ->setTableActionData(['is_percentage' => true, 'percentage' => 25])
            ->assertTableActionDataSet(['amount' => 500.0]);
    }

    public function test_amount_field_stays_manually_editable_when_percentage_mode_is_off(): void
    {
        $user = User::factory()->create();
        $salesOrder = $this->jobWithApprovedValue(2000);
        $salesOrder->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($salesOrder->company);
        app(Tenancy::class)->set($salesOrder->company);

        Livewire::test(MilestonesRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->mountTableAction('create')
            ->setTableActionData(['amount' => 750])
            ->assertTableActionDataSet(['amount' => 750]);
    }
}
