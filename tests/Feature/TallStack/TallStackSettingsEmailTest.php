<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSettingsEmail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The invoice/quote/quotation/payment email BODY fields moved from a plain
 * <x-input> to TallStackUI's <x-editor> — subject stays plain text. See
 * resources/views/livewire/tallstack-settings-email.blade.php and
 * App\Support\Html\RichTextSanitizer.
 */
class TallStackSettingsEmailTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_saving_persists_a_formatted_html_body(): void
    {
        $formatted = '<p>Hi {{contact_name}}, your invoice is ready.</p><ul><li>Pay online</li></ul>';

        Livewire::test(TallStackSettingsEmail::class, ['company' => $this->company])
            ->set('invoice_email_subject', 'Invoice {{invoice_number}}')
            ->set('invoice_email_body', $formatted)
            ->call('save');

        $settings = CompanySetting::query()->where('company_id', $this->company->id)->first();

        $this->assertSame($formatted, $settings->invoice_email_body);
    }

    public function test_saving_sanitizes_a_malicious_body_before_persisting(): void
    {
        Livewire::test(TallStackSettingsEmail::class, ['company' => $this->company])
            ->set('invoice_email_body', '<p onmouseover="alert(1)">Hi</p><script>alert("xss")</script>')
            ->call('save');

        $settings = CompanySetting::query()->where('company_id', $this->company->id)->first();

        $this->assertSame('<p>Hi</p>', $settings->invoice_email_body);
    }

    /** CompanySetting::mail_config (App\Services\CompanyMailerResolver) — opt-in per company, previously the single app-wide .env mailer was the only option, with no way to configure a company's own SMTP/API credentials at all. */
    public function test_saving_a_mail_host_persists_the_full_config_as_one_encrypted_array(): void
    {
        Livewire::test(TallStackSettingsEmail::class, ['company' => $this->company])
            ->set('mail_host', 'smtp.mailgun.org')
            ->set('mail_port', 587)
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'acme@mailgun.org')
            ->set('mail_password', 'secret-api-key')
            ->set('mail_from_address', 'billing@acme.test')
            ->set('mail_from_name', 'Acme Billing')
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::query()->where('company_id', $this->company->id)->first();

        $this->assertSame([
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'acme@mailgun.org',
            'password' => 'secret-api-key',
            'from_address' => 'billing@acme.test',
            'from_name' => 'Acme Billing',
        ], $settings->mail_config);
    }

    /** A blank host is a legitimate "use the app's own default mailer" state, not an error — must never leave a stale/partial config behind. */
    public function test_leaving_the_mail_host_blank_clears_any_existing_config(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'mail_config' => ['host' => 'smtp.old.test', 'port' => 587],
        ]);

        Livewire::test(TallStackSettingsEmail::class, ['company' => $this->company])
            ->set('mail_host', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(CompanySetting::query()->where('company_id', $this->company->id)->value('mail_config'));
    }

    public function test_an_invalid_mail_encryption_value_fails_validation(): void
    {
        Livewire::test(TallStackSettingsEmail::class, ['company' => $this->company])
            ->set('mail_host', 'smtp.mailgun.org')
            ->set('mail_encryption', 'rot13')
            ->call('save')
            ->assertHasErrors(['mail_encryption']);
    }
}
