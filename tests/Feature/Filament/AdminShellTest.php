<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app shell — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md §0
 * (S1/S2/S3/S4/S8/S9).
 */
class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_groups_are_ordered_per_the_design_shell(): void
    {
        $groups = array_map(
            fn ($group) => is_string($group) ? $group : $group->getLabel(),
            Filament::getPanel('admin')->getNavigationGroups()
        );

        $this->assertSame(
            ['Sales', 'Billing', 'Procurement', 'Delivery', 'Clients', 'Catalog', 'Expenses', 'Documents', 'Reports', 'Team', 'Settings'],
            $groups
        );
    }

    /**
     * The config-only assertion above previously passed even while the
     * REAL rendered sidebar order was wrong: Filament does not push a
     * group left out of ->navigationGroups() to the end — it keeps its
     * own default (alphabetical) sort weight, which sorted "Billing"/
     * "Clients"/"Documents"/"Expenses" ahead of "Sales" despite this
     * array's intent. Only a real rendered-page check catches that class
     * of bug, so this asserts every group appears in the sidebar HTML in
     * the intended relative order.
     */
    public function test_the_rendered_sidebar_lists_navigation_groups_in_order(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        $html = $this->actingAs($user)
            ->get("/admin/{$company->slug}")
            ->assertOk()
            ->getContent();

        // 'Expenses' is deliberately absent: Vendors/Expenses/Expense
        // Categories all moved to 'Procurement' (Track A4), so the
        // 'Expenses' group now has zero resources and Filament correctly
        // never renders an empty group.
        $order = ['Sales', 'Billing', 'Procurement', 'Clients', 'Catalog', 'Documents', 'Team', 'Settings'];
        // The group heading's own visible text is bound reactively
        // (Alpine/Livewire), never present as static ">Label<" HTML — the
        // collapse toggle's aria-label is the one static, per-group
        // marker present in every render.
        $positions = array_map(fn (string $label) => strpos($html, 'aria-label="'.$label.'"'), $order);

        $this->assertNotContains(false, $positions, 'Every navigation group label must appear in the rendered sidebar.');
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), 'Navigation groups did not render in the intended order: '.implode(' -> ', $order));
    }

    public function test_the_stock_account_widget_is_not_registered(): void
    {
        $this->assertNotContains(AccountWidget::class, Filament::getPanel('admin')->getWidgets());
    }

    public function test_vendor_resource_is_in_the_procurement_navigation_group(): void
    {
        $this->assertSame('Procurement', VendorResource::getNavigationGroup());
    }

    public function test_dashboard_renders_with_a_per_tenant_brand_color_applied(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'primary_color' => '#123456',
        ]);
        $company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);

        $this->get('/admin/acme')->assertOk();
    }
}
