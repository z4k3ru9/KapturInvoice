<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot "run the legacy InvoiceNinja import over HTTP" route — see
 * DeployBootstrapController's docblock for why an HTTP route exists at all
 * (cPanel host with cron but no Terminal/SSH). This is a separate route
 * from the bootstrap one deliberately: importing real historical financial
 * data is a bigger, more deliberate action than standing up an empty
 * schema, and `{company}` is resolved only against the pre-approved
 * `config('deploy.imports')` allow-list — never an arbitrary artisan
 * command or DB connection name taken from the request — so this can only
 * ever run one of the two specific, already-reviewed import commands.
 *
 * Same `config('deploy.migrate_token')` gate as DeployBootstrapController
 * (hash_equals, 404 when unset or wrong) plus its route's `throttle`
 * middleware.
 */
class DeployImportController extends Controller
{
    public function __invoke(Request $request, string $company): JsonResponse
    {
        $token = config('deploy.migrate_token');

        abort_if(blank($token), 404);
        abort_unless(hash_equals((string) $token, (string) $request->query('token')), 404);

        $import = config("deploy.imports.{$company}");

        abort_if($import === null, 404, "No pre-approved import configured for company [{$company}].");

        $params = [
            'company' => $company,
            '--connection' => $import['connection'],
        ];

        if ($request->boolean('resume')) {
            $params['--resume'] = true;
        }

        $exitCode = Artisan::call($import['command'], $params);

        return response()->json([
            'ok' => $exitCode === 0,
            'company' => $company,
            'command' => $import['command'],
            'connection' => $import['connection'],
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }
}
