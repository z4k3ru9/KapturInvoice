<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Covers App\Http\Controllers\DeployImportController — the token-gated
 * HTTP route that runs one of the two pre-approved legacy InvoiceNinja
 * imports (config('deploy.imports')). Mocks the Artisan facade rather than
 * running a real import: that needs an actual restored legacy database on
 * the 'legacy_v5'/'legacy_v5_company_a' connections, which is exactly the
 * kind of environment-specific setup this route exists to avoid needing a
 * Terminal for — the import command's own correctness is covered by
 * ImportInvoiceNinjaV5Test separately. This test only proves the route
 * resolves {company} through the allow-list correctly and never accepts
 * an arbitrary command/connection from the request.
 */
class DeployImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_route_404s_when_no_token_is_configured(): void
    {
        config(['deploy.migrate_token' => null]);

        $this->get(route('deploy.import', ['company' => 'company-a', 'token' => 'anything']))
            ->assertNotFound();
    }

    public function test_the_route_404s_with_a_wrong_token(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get(route('deploy.import', ['company' => 'company-a', 'token' => 'wrong']))
            ->assertNotFound();
    }

    public function test_an_unlisted_company_slug_404s_even_with_a_correct_token(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        $this->get(route('deploy.import', ['company' => 'not-a-real-company', 'token' => 'the-real-token']))
            ->assertNotFound();
    }

    public function test_a_correct_token_runs_the_allow_listed_import_for_company_a(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('import:invoiceninja-v5', [
                'company' => 'company-a',
                '--connection' => 'legacy_v5_company_a',
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('imported ok');

        $this->get(route('deploy.import', ['company' => 'company-a', 'token' => 'the-real-token']))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'company' => 'company-a',
                'command' => 'import:invoiceninja-v5',
                'connection' => 'legacy_v5_company_a',
                'exit_code' => 0,
                'output' => 'imported ok',
            ]);
    }

    public function test_a_correct_token_runs_the_allow_listed_import_for_company_b(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('import:invoiceninja-v5', [
                'company' => 'company-b',
                '--connection' => 'legacy_v5',
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('imported ok');

        $this->get(route('deploy.import', ['company' => 'company-b', 'token' => 'the-real-token']))
            ->assertOk()
            ->assertJsonPath('connection', 'legacy_v5');
    }

    public function test_the_resume_flag_is_forwarded_when_requested(): void
    {
        config(['deploy.migrate_token' => 'the-real-token']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('import:invoiceninja-v5', [
                'company' => 'company-a',
                '--connection' => 'legacy_v5_company_a',
                '--resume' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('resumed ok');

        $this->get(route('deploy.import', ['company' => 'company-a', 'token' => 'the-real-token', 'resume' => '1']))
            ->assertOk();
    }
}
