<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * One-shot "stand up a brand-new production database over HTTP" route —
 * see config/deploy.php's own docblock for why this exists (cPanel hosts
 * with cron but no Terminal/SSH, so there's no way to run a one-off
 * `php artisan migrate --force` by hand).
 *
 * Runs `migrate --force` plus the three idempotent (`updateOrCreate`-based)
 * reference-data seeders — CurrencySeeder/CountrySeeder/CompanySeeder.
 * Deliberately never calls the full DatabaseSeeder: that one also creates
 * `test@example.com` / `password` (Laravel's stock UserFactory default,
 * documented in this repo's own CLAUDE.md) as an `is_super_admin` user —
 * fine for local dev, a real security hole on a public production database.
 * Optionally creates/updates one real Owner user instead, from
 * config('deploy.admin_*') — see that config file.
 *
 * Token-gated (`config('deploy.migrate_token')`, compared with
 * `hash_equals`): refuses with 404 whenever that config is empty, which is
 * the default — nothing here is reachable until DEPLOY_MIGRATE_TOKEN is
 * deliberately set in .env. Rate-limited by the `throttle` middleware on
 * its route to slow down token-guessing. Re-running this route is safe —
 * every step it performs is idempotent.
 */
class DeployBootstrapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('deploy.migrate_token');

        abort_if(blank($token), 404);
        abort_unless(hash_equals((string) $token, (string) $request->query('token')), 404);

        $migrated = Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = trim(Artisan::output());

        Artisan::call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => CountrySeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => CompanySeeder::class, '--force' => true]);

        $admin = $this->createOrUpdateAdmin();

        return response()->json([
            'ok' => true,
            'migrate_exit_code' => $migrated,
            'migrate_output' => $migrateOutput,
            'companies' => Company::query()->count(),
            'currencies' => Currency::query()->count(),
            'countries' => Country::query()->count(),
            'admin_user' => $admin ? $admin->email : null,
        ]);
    }

    /**
     * Creates (or updates the password of, on a repeat visit) the one real
     * Owner user from config('deploy.admin_email'/'admin_password'), and
     * attaches them as Owner to every existing Company. Returns null and
     * does nothing when those two config values aren't both set — the
     * admin-user step is optional, e.g. for a re-run that only needs to
     * pick up a new migration.
     */
    private function createOrUpdateAdmin(): ?User
    {
        $email = config('deploy.admin_email');
        $password = config('deploy.admin_password');

        if (blank($email) || blank($password)) {
            return null;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('deploy.admin_name', 'Owner'),
                'password' => Hash::make($password),
                'is_super_admin' => true,
            ]
        );

        Company::query()->each(function (Company $company) use ($user): void {
            $company->users()->syncWithoutDetaching([$user->id => ['role' => 'owner', 'is_active' => true]]);
        });

        return $user;
    }
}
