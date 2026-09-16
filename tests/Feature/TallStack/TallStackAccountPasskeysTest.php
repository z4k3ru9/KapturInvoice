<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackAccountPasskeys;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the server-side half of passkey management (App\Livewire\TallStackAccountPasskeys)
 * — listing and deleting rows. The registration ceremony itself
 * (window.Passkeys.register()) is entirely client-side WebAuthn, which
 * needs a real authenticator and cannot be exercised from PHPUnit; it was
 * verified manually in a real browser instead (localhost origin, since
 * WebAuthn refuses a bare IP like 127.0.0.1).
 */
class TallStackAccountPasskeysTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'owner']);
    }

    public function test_a_guest_cannot_view_the_passkeys_page(): void
    {
        $this->get(route('tallstack.account.passkeys', $this->company))
            ->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_current_users_own_passkeys(): void
    {
        $this->actingAs($this->user);

        $mine = $this->user->passkeys()->create([
            'name' => 'My Laptop',
            'credential_id' => 'cred-mine',
            'credential' => ['type' => 'public-key'],
        ]);

        $otherUser = User::factory()->create();
        $otherUser->passkeys()->create([
            'name' => 'Someone Elses Phone',
            'credential_id' => 'cred-other',
            'credential' => ['type' => 'public-key'],
        ]);

        Livewire::test(TallStackAccountPasskeys::class, ['company' => $this->company])
            ->assertSee('My Laptop')
            ->assertDontSee('Someone Elses Phone');

        $this->assertTrue($this->user->fresh()->hasPasskeysEnabled());
        $this->assertSame(1, $this->user->passkeys()->count());
        $this->assertSame($mine->id, $this->user->passkeys()->first()->id);
    }

    public function test_a_user_can_delete_their_own_passkey(): void
    {
        $this->actingAs($this->user);

        $passkey = $this->user->passkeys()->create([
            'name' => 'Old Phone',
            'credential_id' => 'cred-delete-me',
            'credential' => ['type' => 'public-key'],
        ]);

        Livewire::test(TallStackAccountPasskeys::class, ['company' => $this->company])
            ->call('delete', $passkey->id);

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_a_user_cannot_delete_another_users_passkey(): void
    {
        $this->actingAs($this->user);

        $otherUser = User::factory()->create();
        $theirPasskey = $otherUser->passkeys()->create([
            'name' => 'Not Yours',
            'credential_id' => 'cred-not-yours',
            'credential' => ['type' => 'public-key'],
        ]);

        Livewire::test(TallStackAccountPasskeys::class, ['company' => $this->company])
            ->call('delete', $theirPasskey->id);

        $this->assertDatabaseHas('passkeys', ['id' => $theirPasskey->id]);
    }
}
