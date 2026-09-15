<?php

namespace Tests\Feature\Console;

use App\Enums\ReconciliationStatus;
use App\Models\Company;
use App\Models\ReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `migration:reconcile` — Phase 07's cutover sequence needs to "Reconcile
 * again" and run a "next-business-day post-cutover reconciliation"
 * without repeating a full import. See
 * docs/rebuild/specs/07-migration-and-cutover/Specs.md.
 */
class ReconcileMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const CONN = 'legacy_test_reconcile_v4';

    public function test_reconciling_against_the_v4_source_persists_a_run_without_a_full_import(): void
    {
        $conn = self::CONN;

        config(['database.connections.'.$conn => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        Schema::connection($conn)->create('clients', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
        });
        Schema::connection($conn)->create('vendors', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->boolean('is_deleted')->default(false);
        });
        Schema::connection($conn)->create('invoices', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->decimal('amount', 15, 2)->default(0);
        });
        Schema::connection($conn)->create('payments', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->decimal('amount', 15, 2)->default(0);
            $t->boolean('is_deleted')->default(false);
        });

        $db = fn (string $table) => DB::connection($conn)->table($table);
        $db('clients')->insert(['id' => 1, 'account_id' => 1]);
        $db('invoices')->insert(['id' => 1, 'account_id' => 1, 'amount' => 100000]);
        $db('payments')->insert(['id' => 1, 'account_id' => 1, 'amount' => 50000]);

        // A target company with no imported rows at all — deliberately
        // "out of sync" so the run must report a real discrepancy rather
        // than trivially matching zero-vs-zero.
        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('migration:reconcile', [
            'company' => 'test-co',
            '--source' => 'v4',
            '--legacy-account-id' => 1,
            '--connection' => $conn,
        ])->assertExitCode(0);

        $run = ReconciliationRun::where('company_id', $company->id)->sole();

        $this->assertSame(ReconciliationStatus::Discrepancy, $run->status);
        $this->assertNull($run->migration_batch_id);
        $this->assertSame(1, $run->metrics['source']['clients']);
        $this->assertSame(0, $run->metrics['target']['clients']);
    }

    public function test_an_invalid_source_option_fails_fast(): void
    {
        Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('migration:reconcile', ['company' => 'test-co', '--source' => 'v3'])
            ->assertExitCode(1);
    }
}
