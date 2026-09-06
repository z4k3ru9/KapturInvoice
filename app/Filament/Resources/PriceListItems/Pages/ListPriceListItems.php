<?php

namespace App\Filament\Resources\PriceListItems\Pages;

use App\Filament\Resources\PriceListItems\PriceListItemResource;
use App\Services\PriceListImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListPriceListItems extends ListRecords
{
    protected static string $resource = PriceListItemResource::class;

    // The "update this regularly" self-service path (see
    // docs/price-list-import.md): upload a vendor pricelist .xlsx straight
    // from the admin panel instead of shelling out to `import:pricelist`.
    // Re-uploading the same/an updated file for the same brand refreshes
    // existing rows (upsert on company/brand/sku in PriceListImporter)
    // rather than duplicating them.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import pricelist')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('file')
                        ->label('Vendor pricelist (.xlsx)')
                        ->required()
                        ->disk('local')
                        ->directory('price-list-imports')
                        ->visibility('private')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                    TextInput::make('brand')
                        ->required()
                        ->helperText('Tags every imported row, e.g. "Hikvision" or "HiLook". Re-importing the same brand updates existing rows instead of duplicating them.'),
                ])
                ->action(function (array $data): void {
                    $storedPath = $data['file'];
                    $absolutePath = Storage::disk('local')->path($storedPath);

                    $stats = app(PriceListImporter::class)->import(
                        $absolutePath,
                        Filament::getTenant(),
                        $data['brand'],
                        basename($storedPath)
                    );

                    Storage::disk('local')->delete($storedPath);

                    $body = "{$stats['rows_read']} rows ({$stats['created']} new, {$stats['updated']} updated).";

                    if ($stats['sheets_skipped'] !== []) {
                        $body .= ' Sheets skipped (no recognizable pricelist table): '.implode(', ', $stats['sheets_skipped']).'.';
                    }

                    Notification::make()
                        ->success()
                        ->title('Pricelist imported')
                        ->body($body)
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
