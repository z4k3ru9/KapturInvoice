<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseTotalsCalculator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $taxRateIds = Arr::pull($data, 'tax_rate_ids', []);

        /** @var Expense $expense */
        $expense = static::getModel()::create($data);

        app(ExpenseTotalsCalculator::class)->syncTaxes($expense, $taxRateIds);
        app(ExpenseTotalsCalculator::class)->recalculate($expense);

        return $expense;
    }
}
