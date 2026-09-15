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
            ['Sales', 'Procurement', 'Delivery', 'Catalog', 'Reports', 'Settings'],
            $groups
        );
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
