<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an Owner/Admin invites a new internal user
 * (App\Services\CompanyMembershipService::invite()) — an expiring link the
 * recipient uses to set their own local password. See
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §1: accounts/passwords are
 * local to each deployment, no SSO/social login at launch.
 */
class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public UserInvitation $invitation) {}

    public function build(): self
    {
        $company = $this->invitation->company;
        $url = route('invitations.accept', ['token' => $this->invitation->token]);

        return $this->subject("You've been invited to {$company->name} on KapturInvoice")
            ->view('emails.plain', [
                'body' => "You've been invited to join {$company->name} as {$this->invitation->role->label()}.\n\n"
                    ."Accept your invitation: {$url}\n\n"
                    ."This link expires on {$this->invitation->expires_at->toFormattedDateString()}.",
            ]);
    }
}
