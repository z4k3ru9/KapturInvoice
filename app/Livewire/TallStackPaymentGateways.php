<?php

namespace App\Livewire;

use App\Enums\LocalPaymentMethod;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Services\PaymentGateways\GatewayNotConfiguredException;
use App\Services\PaymentGateways\PaymentGatewayManager;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Payment Gateways register/config screen — see
 * App\Livewire\TallStackVendors's docblock for the established
 * presentation-layer-swap pattern this follows. Reuses
 * App\Models\PaymentGateway and App\Services\PaymentGateways\PaymentGatewayManager
 * unmodified; this is a pure UI swap, no driver/config-encryption/
 * test-connection logic changes.
 *
 * Matches the equivalent pre-TallStackUI Filament payment gateway form
 * field-for-field and that same resource's table "Test connection" action
 * exactly (same PaymentGatewayManager::driverFor() call, same three caught
 * exception types) — only the result surfaces as an inline success/error
 * banner per row (docs/rebuild/outputs/27-filament-parity-gap-prompts.md
 * prompt 20) instead of a Filament Notification toast.
 *
 * Authorization: the equivalent Filament payment gateway resource declared
 * no PaymentGateway-specific Policy of its own, so Filament's
 * mutating actions (Create/Edit/Delete) on it were already gated only by
 * the generic App\Providers\AppServiceProvider::registerCompanyRoleGate()
 * Gate::before hook every BelongsToCompany model gets — CompanyRole::mutatingRoles()
 * (every role except Auditor). Reused here unmodified via Gate::authorize()
 * on save(), so this page enforces exactly what Filament enforces, nothing
 * stricter and nothing looser. Because payment gateway configuration is
 * settings-adjacent and holds encrypted API credentials, VIEWING this page
 * additionally requires App\Policies\CompanyPolicy::viewSettings()
 * (Owner/Admin only) — the exact same gate every other TALL-stack Settings
 * page in this nav group (App\Livewire\TallStackSettingsCompanyTaxes and
 * friends) already applies for the same "Settings" sidebar group, not a
 * new gate invented for this page alone.
 */
#[Layout('components.tallstack.app')]
class TallStackPaymentGateways extends Component
{
    use Interactions;

    public Company $company;

    public bool $showModal = false;

    public ?int $editingId = null;

    /** @var array<int, array{success: bool, title: string, message: ?string, at: string}> */
    public array $testResults = [];

    // --- Gateway -----------------------------------------------------
    public ?string $name = null;

    public ?string $driver = null;

    public bool $is_enabled = true;

    // --- Credentials (all drivers) ------------------------------------
    public ?string $configApiKey = null;

    // --- Local API (Indonesia) connection -----------------------------
    public ?string $configBaseUrl = null;

    public ?string $configMerchantId = null;

    /** @var array<int, string> */
    public array $configMethods = [];

    // --- Checkout options ----------------------------------------------
    /** @var array<int, string> */
    public array $accepted_credit_cards = [];

    public bool $show_address = false;

    public bool $require_cvv = false;

    // --- Surcharge fee ---------------------------------------------------
    public ?string $fee_amount = null;

    public ?string $fee_percent = null;

    public ?string $fee_tax_name = null;

    public ?string $fee_tax_rate = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);
    }

    public function driverOptions(): array
    {
        return [
            ['label' => 'Local API (Indonesia)', 'value' => 'local_api'],
            ['label' => 'Stripe', 'value' => 'stripe'],
            ['label' => 'Midtrans', 'value' => 'midtrans'],
            ['label' => 'Xendit', 'value' => 'xendit'],
            ['label' => 'PayPal', 'value' => 'paypal'],
            ['label' => 'Manual / offline', 'value' => 'manual'],
        ];
    }

    public function methodOptions(): array
    {
        return collect(LocalPaymentMethod::cases())
            ->map(fn (LocalPaymentMethod $method) => ['label' => $method->getLabel(), 'value' => $method->value])
            ->all();
    }

    public function creditCardOptions(): array
    {
        return [
            ['label' => 'Visa', 'value' => 'visa'],
            ['label' => 'Mastercard', 'value' => 'mastercard'],
            ['label' => 'American Express', 'value' => 'amex'],
            ['label' => 'JCB', 'value' => 'jcb'],
        ];
    }

    public function create(): void
    {
        Gate::authorize('create', PaymentGateway::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $gateway = $this->findScoped($id);

        if (! $gateway) {
            return;
        }

        $config = $gateway->config ?? [];

        $this->editingId = $gateway->id;
        $this->name = $gateway->name;
        $this->driver = $gateway->driver;
        $this->is_enabled = (bool) $gateway->is_enabled;
        $this->configApiKey = $config['api_key'] ?? null;
        $this->configBaseUrl = $config['base_url'] ?? null;
        $this->configMerchantId = $config['merchant_id'] ?? null;
        $this->configMethods = $config['methods'] ?? [];
        $this->accepted_credit_cards = $gateway->accepted_credit_cards ?? [];
        $this->show_address = (bool) $gateway->show_address;
        $this->require_cvv = (bool) $gateway->require_cvv;
        $this->fee_amount = $gateway->fee_amount !== null ? (string) $gateway->fee_amount : null;
        $this->fee_percent = $gateway->fee_percent !== null ? (string) $gateway->fee_percent : null;
        $this->fee_tax_name = $gateway->fee_tax_name;
        $this->fee_tax_rate = $gateway->fee_tax_rate !== null ? (string) $gateway->fee_tax_rate : null;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'in:local_api,stripe,midtrans,xendit,paypal,manual'],
            'is_enabled' => ['boolean'],
            'configApiKey' => ['nullable', 'string'],
            'configBaseUrl' => ['nullable', 'required_if:driver,local_api', 'url'],
            'configMerchantId' => ['nullable', 'string', 'max:255'],
            'configMethods' => ['array'],
            'configMethods.*' => ['string', 'in:'.collect(LocalPaymentMethod::cases())->map->value->implode(',')],
            'accepted_credit_cards' => ['array'],
            'accepted_credit_cards.*' => ['string', 'in:visa,mastercard,amex,jcb'],
            'show_address' => ['boolean'],
            'require_cvv' => ['boolean'],
            'fee_amount' => ['nullable', 'numeric'],
            'fee_percent' => ['nullable', 'numeric'],
            'fee_tax_name' => ['nullable', 'string', 'max:255'],
            'fee_tax_rate' => ['nullable', 'numeric'],
        ]);

        $config = array_filter([
            'api_key' => $data['configApiKey'] ?: null,
            'base_url' => $data['driver'] === 'local_api' ? ($data['configBaseUrl'] ?: null) : null,
            'merchant_id' => $data['driver'] === 'local_api' ? ($data['configMerchantId'] ?: null) : null,
            'methods' => $data['driver'] === 'local_api' ? $data['configMethods'] : null,
        ], fn ($value) => $value !== null && $value !== []);

        $payload = [
            'name' => $data['name'],
            'driver' => $data['driver'],
            'is_enabled' => $data['is_enabled'],
            'config' => $config,
            'accepted_credit_cards' => $data['accepted_credit_cards'],
            'show_address' => $data['show_address'],
            'require_cvv' => $data['require_cvv'],
            'fee_amount' => $data['fee_amount'] ?? 0,
            'fee_percent' => $data['fee_percent'] ?? 0,
            'fee_tax_name' => $data['fee_tax_name'],
            'fee_tax_rate' => $data['fee_tax_rate'],
        ];

        if ($this->editingId) {
            $gateway = $this->findScoped($this->editingId);

            if (! $gateway) {
                return;
            }

            Gate::authorize('update', $gateway);

            $gateway->update($payload);
            $this->toast()->success('Payment gateway saved.')->send();
        } else {
            Gate::authorize('create', PaymentGateway::class);

            $payload['company_id'] = $this->company->id;
            PaymentGateway::create($payload);
            $this->toast()->success('Payment gateway created.')->send();
        }

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Fires the real driver's test call — identical to
     * PaymentGatewaysTable's "Test connection" row action (same
     * PaymentGatewayManager::driverFor()->testConnection() call, same
     * three caught exception types), storing the result for the inline
     * banner instead of a Filament Notification.
     */
    public function testConnection(int $id): void
    {
        $gateway = $this->findScoped($id);

        if (! $gateway) {
            return;
        }

        try {
            $ok = app(PaymentGatewayManager::class)->driverFor($gateway)->testConnection();

            $this->testResults[$id] = $ok
                ? ['success' => true, 'title' => 'Connected', 'message' => null, 'at' => now()->toDateTimeString()]
                : ['success' => false, 'title' => 'Gateway responded, but the check failed', 'message' => null, 'at' => now()->toDateTimeString()];
        } catch (GatewayNotConfiguredException $e) {
            $this->testResults[$id] = ['success' => false, 'title' => 'Not configured', 'message' => $e->getMessage(), 'at' => now()->toDateTimeString()];
        } catch (ConnectionException $e) {
            $this->testResults[$id] = ['success' => false, 'title' => 'Could not reach the gateway', 'message' => $e->getMessage(), 'at' => now()->toDateTimeString()];
        } catch (RuntimeException $e) {
            $this->testResults[$id] = ['success' => false, 'title' => 'No driver for this gateway type', 'message' => $e->getMessage(), 'at' => now()->toDateTimeString()];
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = null;
        $this->driver = null;
        $this->is_enabled = true;
        $this->configApiKey = null;
        $this->configBaseUrl = null;
        $this->configMerchantId = null;
        $this->configMethods = [];
        $this->accepted_credit_cards = [];
        $this->show_address = false;
        $this->require_cvv = false;
        $this->fee_amount = null;
        $this->fee_percent = null;
        $this->fee_tax_name = null;
        $this->fee_tax_rate = null;
    }

    /** Never trust a bare `PaymentGateway::find()` here — always re-check company ownership. */
    private function findScoped(?int $id): ?PaymentGateway
    {
        if (! $id) {
            return null;
        }

        $gateway = PaymentGateway::find($id);

        if (! $gateway || $gateway->company_id !== $this->company->id) {
            return null;
        }

        return $gateway;
    }

    public function render(): View
    {
        $base = PaymentGateway::query()->where('company_id', $this->company->id);

        $gateways = (clone $base)
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentGateway $gateway) => [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'driver' => $gateway->driver,
                'is_enabled' => (bool) $gateway->is_enabled,
                'accepted_credit_cards' => $gateway->accepted_credit_cards ?? [],
            ]);

        return view('livewire.tallstack-payment-gateways', [
            'gateways' => $gateways,
            'driverOptions' => $this->driverOptions(),
            'methodOptions' => $this->methodOptions(),
            'creditCardOptions' => $this->creditCardOptions(),
            'stats' => [
                'total' => (clone $base)->count(),
                'enabled' => (clone $base)->where('is_enabled', true)->count(),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'payment-gateways',
            'title' => 'Payment Gateways',
        ]);
    }
}
