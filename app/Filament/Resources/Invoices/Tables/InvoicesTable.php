<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Actions\Billing\AmendIssuedInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidAndReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TaxCategory;
use App\Filament\Support\DownloadPdfAction;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\BillingMailer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use RuntimeException;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_recurring')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(InvoiceType::class),
                SelectFilter::make('status')->options(InvoiceStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('send')
                    ->label(fn (Invoice $record) => $record->status === InvoiceStatus::Draft ? 'Send' : 'Resend')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        try {
                            app(BillingMailer::class)->sendInvoice($record);

                            Notification::make()
                                ->success()->seconds(4)
                                ->title('Invoice sent')
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->danger()->persistent()
                                ->title('Could not send invoice')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                DownloadPdfAction::invoice(),

                // Phase 04 (docs/rebuild/specs/04-billing-and-receivables):
                // Draft/Approved -> Issued via the tax-snapshotting action —
                // never a bare status edit. IssueInvoice itself advances
                // Draft -> Approved -> Issued in one call.
                Action::make('issue')
                    ->label('Issue')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    // Scoped to a plain, non-recurring Invoice row — the
                    // `invoices` table also holds type=Quote rows
                    // (App\Filament\Resources\Quotes) and `is_recurring`
                    // template rows (App\Filament\Resources\
                    // RecurringInvoices), which must never be issued this
                    // way (a Codex review finding on PR #4 — also enforced
                    // inside IssueInvoice itself, not just here).
                    ->visible(fn (Invoice $record) => $record->type === InvoiceType::Invoice
                        && ! $record->is_recurring
                        && in_array($record->status, [InvoiceStatus::Draft, InvoiceStatus::Approved], true))
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        try {
                            app(IssueInvoice::class)->issue($record, auth()->user());

                            Notification::make()->success()->seconds(4)->title('Invoice issued')->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not issue invoice')->body($e->getMessage())->send();
                        }
                    }),

                // "Corrections use amendment or void-and-reissue with reason
                // and new number" — the original is never edited/deleted;
                // both create a brand-new invoice and leave this one intact.
                Action::make('amend')
                    ->label('Amend')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (Invoice $record) => $record->status->canTransitionTo(InvoiceStatus::Amended))
                    ->schema(self::correctionSchema())
                    ->action(function (Invoice $record, array $data) {
                        try {
                            $new = app(AmendIssuedInvoice::class)->amend($record, $data['reason'], self::mapItems($data['items']), auth()->user());

                            Notification::make()->success()->seconds(4)->title('Invoice amended')->body("Created amendment #{$new->number}.")->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not amend invoice')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('voidAndReissue')
                    ->label('Void & reissue')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Invoice $record) => $record->status->canTransitionTo(InvoiceStatus::Void))
                    ->schema(self::correctionSchema())
                    ->action(function (Invoice $record, array $data) {
                        try {
                            $new = app(VoidAndReissueInvoice::class)->voidAndReissue($record, $data['reason'], self::mapItems($data['items']), auth()->user());

                            Notification::make()->success()->seconds(4)->title('Invoice voided and reissued')->body("Created #{$new->number}.")->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->persistent()->title('Could not void and reissue invoice')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /** @return array<int, mixed> */
    private static function correctionSchema(): array
    {
        return [
            Textarea::make('reason')
                ->required()
                ->columnSpanFull(),
            Repeater::make('items')
                ->label('Corrected line items')
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Product::query()
                            ->where('company_id', Filament::getTenant()?->id)
                            ->where('name', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('name', 'id'))
                        ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name),
                    TextInput::make('title')->required(),
                    TextInput::make('description'),
                    TextInput::make('quantity')->numeric()->required()->default(1),
                    TextInput::make('unit_cost')->numeric()->required()->default(0),
                    TextInput::make('discount')->numeric()->default(0),
                    Toggle::make('discount_is_percentage'),
                    Select::make('tax_category')
                        ->options(TaxCategory::class)
                        ->helperText('Leave blank to use the product\'s own category.'),
                ])
                ->columns(3)
                ->minItems(1)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function mapItems(array $items): array
    {
        return array_map(fn (array $item) => [
            'product_id' => $item['product_id'] ?? null,
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'unit_cost' => $item['unit_cost'],
            'discount' => $item['discount'] ?? 0,
            'discount_is_percentage' => $item['discount_is_percentage'] ?? false,
            'tax_category' => filled($item['tax_category'] ?? null) ? TaxCategory::from($item['tax_category']) : null,
        ], $items);
    }
}
