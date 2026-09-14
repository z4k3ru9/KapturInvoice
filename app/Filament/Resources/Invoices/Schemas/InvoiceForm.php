<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PricingMode;
use App\Models\Client;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class InvoiceForm
{
    // company_id is set automatically from the active Filament tenant
    // (Invoice::company()) — client/self relationship() selects are
    // tenant-scoped via App\Models\Concerns\BelongsToCompany.
    // subtotal/tax_total/total/balance are recomputed from the line items
    // by App\Services\InvoiceTotalsCalculator — not directly editable.
    //
    // Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa): every
    // field listed in App\Filament\Resources\Invoices\Pages\
    // EditInvoice::autosaveFields() gets `->live(debounce: '1750ms')`
    // plus the shared autosave hook below — Livewire's debounced
    // wire:model already commits on blur too, so one mechanism covers
    // both halves of "after 1.5-2 seconds of inactivity and on blur".
    // Guarded to no-op on CreateInvoice (which reuses this same Schema
    // but has no persisted record/draft_version yet).
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                // Prefills the client's default discount (§
                                // "Billing defaults" on ClientForm) — still
                                // just a starting point, editable below like
                                // any other field.
                                if ($client = Client::find($state)) {
                                    $set('discount', $client->default_discount);
                                    $set('discount_is_percentage', $client->default_discount_is_percentage);
                                }
                            })
                            ->columnSpanFull(),
                        Select::make('type')
                            ->options(InvoiceType::class)
                            ->default(InvoiceType::Invoice)
                            ->required(),
                        Select::make('status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft)
                            ->required()
                            // Codex review finding on PR #4: the helper
                            // text alone didn't stop a direct save from
                            // setting one of these action-owned states —
                            // saving Issued this way would bypass
                            // App\Actions\Billing\IssueInvoice entirely
                            // (no number, no tax snapshot, no audit
                            // event). Disabled, not removed, so an
                            // already-Issued/Void/Amended record still
                            // displays its real status correctly.
                            ->disableOptionWhen(fn (string $value): bool => in_array($value, [
                                InvoiceStatus::Issued->value,
                                InvoiceStatus::Void->value,
                                InvoiceStatus::Amended->value,
                            ], true))
                            ->helperText('Issued/Void/Amended states are reached only through the Issue/Amend/Void & reissue table actions, never here.'),
                        Select::make('pricing_mode')
                            ->label('Pricing mode')
                            ->options(PricingMode::class)
                            ->default(PricingMode::Exclusive)
                            ->required()
                            ->helperText('One tax mode per document — see FINALIZED-DECISIONS.md §3.'),
                        TextInput::make('number')
                            ->helperText('Leave blank to auto-assign from the company numbering sequence.'),
                        TextInput::make('po_number')
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        DatePicker::make('invoice_date')
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        DatePicker::make('due_date')
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        TextInput::make('currency_code')
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        TextInput::make('discount')
                            ->numeric()
                            ->default(0)
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        Toggle::make('discount_is_percentage')
                            ->label('Discount is a percentage')
                            ->live()
                            ->afterStateUpdated(static::autosaveHook()),
                        TextInput::make('legacy_invoice_id')
                            ->numeric()
                            ->helperText('Legacy InvoiceNinja invoice id, for import traceability.'),
                    ]),
                Section::make('Recurring')
                    ->columns(2)
                    ->collapsed(fn (?Invoice $record) => ! $record?->is_recurring)
                    ->schema([
                        Toggle::make('is_recurring')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('recurring_frequency')
                            ->helperText('weekly, monthly, quarterly, yearly…')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        Toggle::make('auto_bill')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        DatePicker::make('recurring_start_date')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                        DatePicker::make('recurring_end_date')
                            ->visible(fn (Get $get) => $get('is_recurring')),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('terms')
                            ->columnSpanFull()
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        Textarea::make('public_notes')
                            ->columnSpanFull()
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        Textarea::make('private_notes')
                            ->columnSpanFull()
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                        Textarea::make('footer')
                            ->columnSpanFull()
                            ->live(debounce: '1750ms')
                            ->afterStateUpdated(static::autosaveHook()),
                    ]),
                Html::make(fn ($livewire) => method_exists($livewire, 'autosaveDraft')
                    ? new HtmlString(view('filament.components.autosave-status', ['livewire' => $livewire])->render())
                    : null)
                    ->visibleOn('edit'),
                Section::make('Totals')
                    ->description('Recomputed automatically from the line items below.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('tax_total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('total')->numeric()->disabled()->dehydrated(false),
                        TextInput::make('balance')->numeric()->disabled()->dehydrated(false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    /**
     * @return \Closure(mixed $livewire): void
     */
    private static function autosaveHook(): \Closure
    {
        return function ($livewire): void {
            if (method_exists($livewire, 'autosaveDraft')) {
                $livewire->autosaveDraft();
            }
        };
    }
}
