<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseTotalsCalculator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Expense $record */
        $record = $this->getRecord();

        $data['tax_rate_ids'] = $record->taxes()->pluck('tax_rate_id')->filter()->values()->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $taxRateIds = Arr::pull($data, 'tax_rate_ids', []);

        /** @var Expense $record */
        $record->update($data);

        app(ExpenseTotalsCalculator::class)->syncTaxes($record, $taxRateIds);
        app(ExpenseTotalsCalculator::class)->recalculate($record);

        return $record;
    }
}
