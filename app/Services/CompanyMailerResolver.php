<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Resolves which mailer a `BillingMailer`/`QuotationMailer` send should
 * actually go through — the company's own configured SMTP transport
 * (`CompanySetting::mail_config`, set from the Email & Reminders settings
 * page) if one is on file, otherwise the app's own default `.env` mailer
 * unchanged. Mirrors `App\Services\PaymentGateways\PaymentGatewayManager`'s
 * own "resolve the right thing for this model" shape.
 *
 * Registers a fresh `mail.mailers.company_{id}` config entry at runtime
 * (Laravel's `MailManager` resolves mailer config lazily by name, so
 * nothing needs to exist in `config/mail.php` ahead of time) rather than
 * hand-building a Symfony transport — this is the standard Laravel
 * pattern for a per-tenant mail transport and keeps every other mailer
 * concern (queueing, markdown, failover) working exactly as configured.
 */
class CompanyMailerResolver
{
    public function for(Company $company): Mailer
    {
        $config = $company->settings?->mail_config;

        if (blank($config) || blank($config['host'] ?? null)) {
            return Mail::mailer(config('mail.default'));
        }

        $name = "company_{$company->id}";

        Config::set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'host' => $config['host'],
            'port' => $config['port'] ?? 587,
            'encryption' => $config['encryption'] ?: null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
        ]);

        return Mail::mailer($name);
    }
}
