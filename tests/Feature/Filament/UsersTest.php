<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->otherCompany->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_resource_index_and_create_pages_render(): void
    {
        $this->get(UserResource::getUrl('index', tenant: $this->company))->assertOk();
        $this->get(UserResource::getUrl('create', tenant: $this->company))->assertOk();
    }

    public function test_only_users_belonging_to_the_current_company_are_listed(): void
    {
        $onlyOtherCompanyUser = User::factory()->create(['name' => 'Not Here']);
        $this->otherCompany->users()->attach($onlyOtherCompanyUser, ['role' => 'member']);

        $this->get(UserResource::getUrl('index', tenant: $this->company))
            ->assertOk()
            ->assertDontSee('Not Here');
    }

    public function test_creating_a_user_attaches_them_to_the_current_tenant(): void
    {
        $this->get(UserResource::getUrl('create', tenant: $this->company))->assertOk();

        Livewire::test(CreateUser::class, ['tenant' => $this->company])
            ->fillForm([
                'name' => 'New Teammate',
                'email' => 'teammate@example.com',
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $newUser = User::where('email', 'teammate@example.com')->firstOrFail();

        $this->assertTrue($this->company->users()->whereKey($newUser->id)->exists());
    }
}
