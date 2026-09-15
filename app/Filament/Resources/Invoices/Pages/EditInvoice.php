<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\AutosavesDraft;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditInvoice extends EditRecord
{
    use AutosavesDraft;

    protected static string $resource = InvoiceResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->initializeAutosaveVersion();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            // docs/REFACTOR_PLAN.md drift audit: guards the single-record
            // action the same way InvoicesTable::forceDeleteBulkAction()
            // guards the bulk one — otherwise this page let a Draft-only
            // rule be bypassed entirely from a single Trashed record.
            ForceDeleteAction::make()
                ->before(function (Invoice $record) {
                    if (! InvoicesTable::isSafeToForceDelete($record)) {
                        Notification::make()
                            ->danger()->persistent()
                            ->title('Could not force-delete invoice')
                            ->body('Only a Draft invoice with no payments, allocations, or amendment/void-reissue history can be permanently deleted.')
                            ->send();

                        throw new Halt;
                    }
                }),
            RestoreAction::make(),
        ];
    }

    /**
     * Never `status`, `number`, or any derived total/balance column —
     * only fields a Draft invoice's author is freely editing before
     * issuance. App\Actions\Billing\IssueInvoice/AmendIssuedInvoice/
     * VoidAndReissueInvoice remain the only path that changes status or
     * writes a tax snapshot; autosave can never reach them.
     */
    protected function autosaveFields(): array
    {
        return [
            'po_number', 'invoice_date', 'due_date',
            'discount', 'discount_is_percentage', 'currency_code',
            'terms', 'public_notes', 'private_notes', 'footer',
        ];
    }

    protected function autosaveGuard(): bool
    {
        return $this->getRecord()->status === InvoiceStatus::Draft;
    }
}
