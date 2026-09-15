<?php

namespace App\Filament\Resources\RecurringInvoices;

use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\RecurringInvoices\Pages\CreateRecurringInvoice;
use App\Filament\Resources\RecurringInvoices\Pages\EditRecurringInvoice;
use App\Filament\Resources\RecurringInvoices\Pages\ListRecurringInvoices;
use App\Filament\Resources\RecurringInvoices\Pages\ViewRecurringInvoice;
use App\Filament\Resources\RecurringInvoices\Tables\RecurringInvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * A filtered view onto the same `invoices` table as InvoiceResource
 * (`is_recurring = true` — the recurring *template*, not its generated
 * instances). See docs/filament-admin-layout-design.md §2.1.
 */
class RecurringInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $modelLabel = 'Recurring invoice';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'recurring-invoices';

    /**
     * Recurring invoices/auto-billing are deferred launch scope
     * (docs/REFACTOR_PLAN.md §1.2, Specs.md §3) — kept intact for any
     * already-imported recurring template, just out of the launch sidebar.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringInvoices::route('/'),
            'create' => CreateRecurringInvoice::route('/create'),
            'view' => ViewRecurringInvoice::route('/{record}'),
            'edit' => EditRecurringInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_recurring', true);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->where('is_recurring', true)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
