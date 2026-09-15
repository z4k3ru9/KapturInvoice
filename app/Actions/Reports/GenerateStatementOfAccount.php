<?php

namespace App\Actions\Reports;

use App\Models\Client;
use App\Models\StatementOfAccount;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use App\Services\Reports\BuildStatementOfAccount;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The only path that persists a real `StatementOfAccount` row — Phase 06B
 * Slice 1 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md): "A generated
 * or sent SOA preserves an immutable PDF snapshot." Assigns a numbered
 * 'SOA' document (App\Services\DocumentNumberGenerator) and freezes the
 * full computed line data from App\Services\Reports\BuildStatementOfAccount
 * into `snapshot`, so the PDF is always rendered from this exact frozen
 * array and never recomputed live afterwards.
 *
 * A *preview* never calls this action — it calls
 * App\Services\Reports\BuildStatementOfAccount::build() directly and
 * renders the same Blade view without an assigned number, so it can be
 * freely regenerated before generation ("A preview may be regenerated
 * before generation.").
 */
class GenerateStatementOfAccount
{
    public function __construct(
        private BuildStatementOfAccount $builder,
        private DocumentNumberGenerator $numberGenerator,
    ) {}

    public function generate(Client $client, DateTimeInterface|string $periodStart, DateTimeInterface|string $periodEnd, ?User $actor = null): StatementOfAccount
    {
        $snapshot = $this->builder->build($client, $periodStart, $periodEnd);

        $number = $this->numberGenerator->next($client->company, 'statement_of_account');

        return StatementOfAccount::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'period_start' => Carbon::parse($periodStart)->toDateString(),
            'period_end' => Carbon::parse($periodEnd)->toDateString(),
            'opening_balance' => $snapshot['opening_balance'],
            'closing_balance' => $snapshot['closing_balance'],
            'number' => $number,
            'generated_by_user_id' => $actor?->id,
            'generated_at' => now(),
            'snapshot' => $snapshot,
        ]);
    }
}
