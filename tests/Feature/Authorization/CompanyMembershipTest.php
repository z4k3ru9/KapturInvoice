<?php

namespace Tests\Feature\Authorization;

use App\Enums\CompanyRole;
use App\Mail\UserInvitationMail;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §1: "Owner/Admin invite
 * internal users by expiring email link; recipients set a local password.
 * Disabling membership blocks access immediately while preserving
 * history." No SSO/social login at launch.
 */
class CompanyMembershipTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    public function test_inviting_a_user_queues_an_invitation_email_with_an_expiring_token(): void
    {
        Mail::fake();

        $invitation = app(CompanyMembershipService::class)
            ->invite($this->company, 'new.hire@example.com', CompanyRole::Sales);

        $this->assertSame($this->company->id, $invitation->company_id);
        $this->assertSame(CompanyRole::Sales, $invitation->role);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertTrue($invitation->isUsable());

        Mail::assertQueued(UserInvitationMail::class, fn ($mail) => $mail->invitation->is($invitation));
    }

    public function test_accepting_a_usable_invitation_creates_an_active_membership(): void
    {
        $invitation = app(CompanyMembershipService::class)
            ->invite($this->company, 'new.hire@example.com', CompanyRole::Sales);

        $user = app(CompanyMembershipService::class)->accept($invitation, 'New Hire', 'a-strong-password');

        $this->assertSame('new.hire@example.com', $user->email);
        $this->assertTrue($user->hasCompanyRole($this->company, CompanyRole::Sales));
        $this->assertTrue($invitation->fresh()->isAccepted());
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $invitation = app(CompanyMembershipService::class)
            ->invite($this->company, 'late@example.com', CompanyRole::Staff, expiresInDays: 1);

        Carbon::setTestNow(now()->addDays(2));

        $this->expectException(RuntimeException::class);
        app(CompanyMembershipService::class)->accept($invitation, 'Late Hire', 'a-strong-password');

        Carbon::setTestNow();
    }

    public function test_an_already_accepted_invitation_cannot_be_reused(): void
    {
        $invitation = app(CompanyMembershipService::class)
            ->invite($this->company, 'once@example.com', CompanyRole::Staff);

        app(CompanyMembershipService::class)->accept($invitation, 'Once Hire', 'a-strong-password');

        $this->expectException(RuntimeException::class);
        app(CompanyMembershipService::class)->accept($invitation->fresh(), 'Once Hire Again', 'another-password');
    }

    public function test_disabling_a_membership_blocks_access_immediately_and_is_audited(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'sales']);

        $this->assertTrue($user->canAccessTenant($this->company));

        app(CompanyMembershipService::class)->disable($this->company, $user, $actor, 'Left the company');

        $this->assertFalse($user->fresh()->canAccessTenant($this->company));

        $event = AuditEvent::where('action', 'membership.disabled')->first();
        $this->assertNotNull($event);
        $this->assertSame($this->company->id, $event->company_id);
        $this->assertSame($user->id, $event->entity_id);
        $this->assertSame('Left the company', $event->reason);
    }

    public function test_disabling_a_membership_preserves_the_membership_row_and_does_not_delete_it(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'sales']);

        app(CompanyMembershipService::class)->disable($this->company, $user, $actor, 'Left the company');

        $this->assertDatabaseHas('company_user', [
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_reenabling_a_membership_restores_access_and_is_audited(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'sales', 'is_active' => false]);

        app(CompanyMembershipService::class)->reenable($this->company, $user, $actor, 'Rehired');

        $this->assertTrue($user->fresh()->canAccessTenant($this->company));
        $this->assertNotNull(AuditEvent::where('action', 'membership.reenabled')->first());
    }
}
