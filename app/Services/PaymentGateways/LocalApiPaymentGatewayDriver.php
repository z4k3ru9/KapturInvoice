<?php

namespace App\Services\PaymentGateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the Indonesian in-house payment API this company plans to
 * charge through (see docs/filament-admin-layout-design.md §3.4) —
 * supports Virtual Account, QRIS, and card, per `App\Enums\LocalPaymentMethod`.
 *
 * ⚠️ The actual base URL, auth scheme, and request/response shape below
 * are placeholders written against a plausible REST contract (bearer
 * token auth, JSON body/response, `POST {base_url}/v1/charges`,
 * `GET {base_url}/v1/charges/{reference}`, `GET {base_url}/v1/ping`) —
 * swap the endpoint paths and `mapResponse()` field mapping for the real
 * provider's API docs once available. Nothing here fakes a successful
 * charge: every method makes (or would make) a real HTTP call, so
 * `Http::fake()` is how tests — and future development against the real
 * API — exercise this without a live provider.
 */
class LocalApiPaymentGatewayDriver implements PaymentGatewayDriver
{
    public function __construct(private PaymentGateway $gateway) {}

    public function charge(Payment $payment, ChargeRequest $request): ChargeResult
    {
        $response = $this->client()
            ->post('/v1/charges', [
                'merchant_id' => $this->config('merchant_id'),
                'external_id' => (string) $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency_code ?: 'IDR',
                'method' => $request->method->value,
                'bank_code' => $request->bankCode,
                'description' => $payment->invoice?->number,
            ])
            ->throw();

        return $this->mapResponse($response->json() ?? []);
    }

    public function checkStatus(Payment $payment): ChargeResult
    {
        $response = $this->client()
            ->get("/v1/charges/{$payment->gateway_reference}")
            ->throw();

        return $this->mapResponse($response->json() ?? []);
    }

    public function handleWebhook(array $payload): ChargeResult
    {
        return $this->mapResponse($payload);
    }

    public function testConnection(): bool
    {
        return $this->client()->get('/v1/ping')->successful();
    }

    protected function client(): PendingRequest
    {
        $baseUrl = $this->config('base_url');
        $apiKey = $this->config('api_key');

        if (blank($baseUrl) || blank($apiKey)) {
            throw new GatewayNotConfiguredException(
                "Gateway [{$this->gateway->name}] is missing its Local API base URL or API key — fill those in before charging."
            );
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withToken($apiKey)
            ->acceptJson();
    }

    protected function config(string $key): mixed
    {
        return ($this->gateway->config ?? [])[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapResponse(array $data): ChargeResult
    {
        $status = match ($data['status'] ?? null) {
            'success', 'settlement', 'paid', 'completed' => PaymentStatus::Completed,
            'failed', 'expired', 'cancelled' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new ChargeResult(
            status: $status,
            providerReference: $data['id'] ?? $data['reference'] ?? null,
            instructions: $data['instructions'] ?? [],
            raw: $data,
        );
    }
}
