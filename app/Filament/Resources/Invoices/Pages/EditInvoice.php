<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\AutosavesDraft;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
            ForceDeleteAction::make(),
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
