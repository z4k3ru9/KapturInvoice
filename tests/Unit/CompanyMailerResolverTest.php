<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\CompanyMailerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `CompanySetting::mail_config` (set from the Email & Reminders settings
 * page) is opt-in — a company with none configured must keep using the
 * app's own default mailer, never fail or silently drop mail.
 */
class CompanyMailerResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_with_no_mail_config_resolves_the_app_default_mailer(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $mailer = app(CompanyMailerResolver::class)->for($company);

        $this->assertNotNull($mailer);
    }

    public function test_a_company_with_mail_config_registers_and_resolves_its_own_smtp_mailer(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::query()->create([
            'company_id' => $company->id,
            'mail_config' => [
                'host' => 'smtp.acme-mail.test',
                'port' => 2525,
                'encryption' => 'tls',
                'username' => 'acme',
                'password' => 'secret-api-key',
            ],
        ]);

        app(CompanyMailerResolver::class)->for($company->fresh(['settings']));

        $this->assertSame('smtp.acme-mail.test', config("mail.mailers.company_{$company->id}.host"));
        $this->assertSame(2525, config("mail.mailers.company_{$company->id}.port"));
        $this->assertSame('secret-api-key', config("mail.mailers.company_{$company->id}.password"));
    }
}
