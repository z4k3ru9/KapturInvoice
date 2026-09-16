<?php

namespace App\Support\Dashboard;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\VendorBillStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\VendorBill;

/**
 * The dashboard's role-aware "Action queue" — see docs/rebuild/DESIGN.md
 * §3 for the approved per-role queue contents and
 * docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/01-shell-dashboard.md D10
 * for the implementation constraint: every link goes to a plain
 * TALL-stack register/index page (no `?tableFilters=` deep link — that
 * needs status-filter wiring this slice deliberately doesn't touch, see
 * the Track A/B split in
 * docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/00-scoped-backlog.md).
 * Auditor sees the same items as Owner/Admin but with every `url` null
 * (read-only, no interactive queue — DESIGN §3).
 *
 * Every item also carries a `category` matching one of the shell's own
 * sidebar nav group names (`resources/views/components/tallstack/app.blade.php`'s
 * `$nav` array) — consumed by that same shell to segment the notification
 * bell's dropdown into labeled sections (App\Support\Dashboard\ActionQueueCategory
 * supplies each category's display label/icon). The Dashboard's own
 * ActionQueueWidget-equivalent table ignores this key and keeps rendering
 * one flat list, so adding it here is additive/non-breaking there.
 */
class ActionQueue
{
    /**
     * @return list<array{label: string, count: int, url: ?string, tone: string, category: string}>
     */
    public static function for(User $user, Company $company): array
    {
        $role = $user->companyRole($company);

        return match ($role) {
            CompanyRole::Owner, CompanyRole::Admin => self::ownerAdminItems($company),
            CompanyRole::Accountant => self::accountantItems($company),
            CompanyRole::Sales => self::salesItems($company),
            CompanyRole::Staff => self::staffItems($company),
            CompanyRole::Auditor => self::readOnly(self::ownerAdminItems($company)),
            default => [],
        };
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function ownerAdminItems(Company $company): array
    {
        return array_values(array_filter([
            self::overdueInvoices($company),
            self::draftJobs($company),
            self::quotationsAwaitingDecision($company),
            self::vendorBillsAwaitingApproval($company),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function accountantItems(Company $company): array
    {
        return array_values(array_filter([
            self::paymentsAwaitingVerification($company),
            self::vendorBillsAwaitingApproval($company),
            self::overdueInvoices($company),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function salesItems(Company $company): array
    {
        return array_values(array_filter([
            self::quotationsAwaitingDecision($company),
            self::draftQuotations($company),
            self::assignedJobs($company),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function staffItems(Company $company): array
    {
        return array_values(array_filter([
            self::jobsAwaitingDelivery($company),
            self::jobsAwaitingHandover($company),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @param list<array{label: string, count: int, url: ?string, tone: string}> $items */
    protected static function readOnly(array $items): array
    {
        return array_map(fn (array $item) => [...$item, 'url' => null], $items);
    }

    protected static function overdueInvoices(Company $company): array
    {
        $count = Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial])
            ->where('due_date', '<', today())
            ->count();

        return [
            'label' => 'Customer invoices overdue',
            'count' => $count,
            'url' => route('tallstack.invoices', $company),
            'tone' => 'danger',
            'category' => 'Billing',
        ];
    }

    protected static function draftJobs(Company $company): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::Draft)->count();

        return [
            'label' => 'Jobs awaiting approval',
            'count' => $count,
            'url' => route('tallstack.jobs', $company),
            'tone' => 'warning',
            'category' => 'Sales',
        ];
    }

    protected static function quotationsAwaitingDecision(Company $company): array
    {
        $count = Quotation::query()->where('status', QuotationStatus::Sent)->count();

        return [
            'label' => 'Quotations awaiting a customer decision',
            'count' => $count,
            'url' => route('tallstack.quotations', $company),
            'tone' => 'info',
            'category' => 'Sales',
        ];
    }

    protected static function vendorBillsAwaitingApproval(Company $company): array
    {
        $count = VendorBill::query()->where('status', VendorBillStatus::Submitted)->count();

        return [
            'label' => 'Vendor bills awaiting approval',
            'count' => $count,
            'url' => route('tallstack.vendor-bills', $company),
            'tone' => 'warning',
            'category' => 'Procurement',
        ];
    }

    protected static function paymentsAwaitingVerification(Company $company): array
    {
        $count = Payment::query()->where('status', PaymentStatus::Pending)->count();

        return [
            'label' => 'Payments awaiting verification',
            'count' => $count,
            'url' => route('tallstack.payments', $company),
            'tone' => 'warning',
            'category' => 'Billing',
        ];
    }

    protected static function draftQuotations(Company $company): array
    {
        $count = Quotation::query()->where('status', QuotationStatus::Draft)->count();

        return [
            'label' => 'Draft quotations',
            'count' => $count,
            'url' => route('tallstack.quotations', $company),
            'tone' => 'gray',
            'category' => 'Sales',
        ];
    }

    protected static function assignedJobs(Company $company): array
    {
        $count = SalesOrder::query()
            ->whereIn('status', [SalesOrderStatus::Approved, SalesOrderStatus::Procurement, SalesOrderStatus::InProgress])
            ->count();

        return [
            'label' => 'Jobs in progress',
            'count' => $count,
            'url' => route('tallstack.jobs', $company),
            'tone' => 'info',
            'category' => 'Sales',
        ];
    }

    protected static function jobsAwaitingDelivery(Company $company): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::InProgress)->count();

        return [
            'label' => 'Jobs ready for delivery',
            'count' => $count,
            'url' => route('tallstack.jobs', $company),
            'tone' => 'info',
            'category' => 'Delivery',
        ];
    }

    protected static function jobsAwaitingHandover(Company $company): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::Delivered)->count();

        return [
            'label' => 'Jobs awaiting handover',
            'count' => $count,
            'url' => route('tallstack.jobs', $company),
            'tone' => 'warning',
            'category' => 'Delivery',
        ];
    }
}
