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
}
