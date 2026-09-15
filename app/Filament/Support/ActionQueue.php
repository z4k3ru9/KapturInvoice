<?php

namespace App\Filament\Support;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\VendorBillStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\VendorBills\VendorBillResource;
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
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md D10
 * for the implementation constraint: every link goes to a resource's plain
 * index page (no `?tableFilters=` deep link — that needs status-filter
 * wiring on InvoicesTable/QuotationsTable/SalesOrdersTable this slice
 * deliberately doesn't touch, see the Track A/B split in
 * 18-stitch-ui-gap-analysis/00-scoped-backlog.md). Auditor sees the same
 * items as Owner/Admin but with every `url` null (read-only, no
 * interactive queue — DESIGN §3).
 */
class ActionQueue
{
    /**
     * @return list<array{label: string, count: int, url: ?string, tone: string}>
     */
    public static function for(User $user, Company $company): array
    {
        $role = $user->companyRole($company);

        return match ($role) {
            CompanyRole::Owner, CompanyRole::Admin => self::ownerAdminItems(),
            CompanyRole::Accountant => self::accountantItems(),
            CompanyRole::Sales => self::salesItems(),
            CompanyRole::Staff => self::staffItems(),
            CompanyRole::Auditor => self::readOnly(self::ownerAdminItems()),
            default => [],
        };
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function ownerAdminItems(): array
    {
        return array_values(array_filter([
            self::overdueInvoices(),
            self::draftJobs(),
            self::quotationsAwaitingDecision(),
            self::vendorBillsAwaitingApproval(),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function accountantItems(): array
    {
        return array_values(array_filter([
            self::paymentsAwaitingVerification(),
            self::vendorBillsAwaitingApproval(),
            self::overdueInvoices(),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function salesItems(): array
    {
        return array_values(array_filter([
            self::quotationsAwaitingDecision(),
            self::draftQuotations(),
            self::assignedJobs(),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @return list<array{label: string, count: int, url: ?string, tone: string}> */
    protected static function staffItems(): array
    {
        return array_values(array_filter([
            self::jobsAwaitingDelivery(),
            self::jobsAwaitingHandover(),
        ], fn (array $item) => $item['count'] > 0));
    }

    /** @param list<array{label: string, count: int, url: ?string, tone: string}> $items */
    protected static function readOnly(array $items): array
    {
        return array_map(fn (array $item) => [...$item, 'url' => null], $items);
    }

    protected static function overdueInvoices(): array
    {
        $count = Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial])
            ->where('due_date', '<', today())
            ->count();

        return [
            'label' => "{$count} customer invoices overdue",
            'count' => $count,
            'url' => InvoiceResource::getUrl('index'),
            'tone' => 'danger',
        ];
    }

    protected static function draftJobs(): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::Draft)->count();

        return [
            'label' => "{$count} jobs awaiting approval",
            'count' => $count,
            'url' => SalesOrderResource::getUrl('index'),
            'tone' => 'warning',
        ];
    }

    protected static function quotationsAwaitingDecision(): array
    {
        $count = Quotation::query()->where('status', QuotationStatus::Sent)->count();

        return [
            'label' => "{$count} quotations awaiting a customer decision",
            'count' => $count,
            'url' => QuotationResource::getUrl('index'),
            'tone' => 'info',
        ];
    }

    protected static function vendorBillsAwaitingApproval(): array
    {
        $count = VendorBill::query()->where('status', VendorBillStatus::Submitted)->count();

        return [
            'label' => "{$count} vendor bills awaiting approval",
            'count' => $count,
            'url' => VendorBillResource::getUrl('index'),
            'tone' => 'warning',
        ];
    }

    protected static function paymentsAwaitingVerification(): array
    {
        $count = Payment::query()->where('status', PaymentStatus::Pending)->count();

        return [
            'label' => "{$count} payments awaiting verification",
            'count' => $count,
            'url' => PaymentResource::getUrl('index'),
            'tone' => 'warning',
        ];
    }

    protected static function draftQuotations(): array
    {
        $count = Quotation::query()->where('status', QuotationStatus::Draft)->count();

        return [
            'label' => "{$count} draft quotations",
            'count' => $count,
            'url' => QuotationResource::getUrl('index'),
            'tone' => 'gray',
        ];
    }

    protected static function assignedJobs(): array
    {
        $count = SalesOrder::query()
            ->whereIn('status', [SalesOrderStatus::Approved, SalesOrderStatus::Procurement, SalesOrderStatus::InProgress])
            ->count();

        return [
            'label' => "{$count} jobs in progress",
            'count' => $count,
            'url' => SalesOrderResource::getUrl('index'),
            'tone' => 'info',
        ];
    }

    protected static function jobsAwaitingDelivery(): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::InProgress)->count();

        return [
            'label' => "{$count} jobs ready for delivery",
            'count' => $count,
            'url' => SalesOrderResource::getUrl('index'),
            'tone' => 'info',
        ];
    }

    protected static function jobsAwaitingHandover(): array
    {
        $count = SalesOrder::query()->where('status', SalesOrderStatus::Delivered)->count();

        return [
            'label' => "{$count} jobs awaiting handover",
            'count' => $count,
            'url' => SalesOrderResource::getUrl('index'),
            'tone' => 'warning',
        ];
    }
}
