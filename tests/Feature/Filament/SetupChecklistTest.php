<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\SetupChecklist;
use App\Filament\Widgets\SetupChecklistWidget;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The first-run setup checklist — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md O1.
 */
class SetupChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_company_has_no_steps_done(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $checklist = SetupChecklist::for($company);

        $this->assertSame(0, $checklist['done']);
        $this->assertFalse(SetupChecklist::isComplete($company));
    }

    public function test_each_step_completes_as_its_underlying_data_appears(): void
    {
        $company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD',
            'address_line_1' => 'Jl. Contoh 1', 'invoice_prefix' => 'ACM-INV-',
        ]);

        $checklist = SetupChecklist::for($company);
        $this->assertTrue($checklist['steps'][0]['done']); // company_profile
        $this->assertTrue($checklist['steps'][1]['done']); // numbering
        $this->assertFalse($checklist['steps'][2]['done']); // catalog
        $this->assertFalse($checklist['steps'][3]['done']); // client
        $this->assertFalse($checklist['steps'][4]['done']); // quotation

        Product::create(['company_id' => $company->id, 'name' => 'Widget']);
        Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $checklist = SetupChecklist::for($company);
        $this->assertTrue($checklist['steps'][2]['done']);
        $this->assertTrue($checklist['steps'][3]['done']);
        $this->assertSame(4, $checklist['done']);
        $this->assertFalse(SetupChecklist::isComplete($company));
    }

    public function test_the_widget_renders_for_an_incomplete_company_and_hides_once_complete(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($company);

        $this->assertTrue(SetupChecklistWidget::canView());

        Livewire::test(SetupChecklistWidget::class)
            ->assertSee('Get set up')
            ->assertSee('Add your first client');
    }
}
