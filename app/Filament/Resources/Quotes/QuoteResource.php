<?php

namespace App\Filament\Resources\Quotes;

use App\Enums\InvoiceType;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
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
 * A filtered view onto the same `invoices` table as InvoiceResource (see
 * docs/invoiceninja-v4-schema-reference.md §2.3's unified-table pattern
 * and docs/filament-admin-layout-design.md §2.1) — reuses InvoiceForm/
 * InvoiceInfolist/ItemsRelationManager as-is, since a quote is structurally
 * identical to an invoice until it's converted.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $modelLabel = 'Quote';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'quotes';

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
        return QuotesTable::configure($table);
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
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'view' => ViewQuote::route('/{record}'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', InvoiceType::Quote);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->where('type', InvoiceType::Quote)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
