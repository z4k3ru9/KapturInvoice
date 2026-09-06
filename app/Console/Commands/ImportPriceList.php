<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\PriceListImporter;
use Illuminate\Console\Command;

/**
 * CLI entry point for App\Services\PriceListImporter — see
 * docs/price-list-import.md. The same import also runs from the admin
 * panel (Catalog > Price List's "Import" action), for updating it
 * regularly without a shell.
 */
class ImportPriceList extends Command
{
    protected $signature = 'import:pricelist
        {company : Target Company slug}
        {file : Path to the vendor pricelist .xlsx file}
        {--brand= : Brand name to tag every imported row with (defaults to the file name without extension)}';

    protected $description = 'Import a vendor pricelist spreadsheet into price_list_items';

    public function handle(PriceListImporter $importer): int
    {
        $company = Company::query()->where('slug', $this->argument('company'))->first();

        if (! $company) {
            $this->error("No company with slug [{$this->argument('company')}].");

            return self::FAILURE;
        }

        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $brand = $this->option('brand') ?: pathinfo($path, PATHINFO_FILENAME);

        $stats = $importer->import($path, $company, $brand, basename($path));

        $this->info("Rows imported: {$stats['rows_read']} ({$stats['created']} new, {$stats['updated']} updated).");

        if ($stats['sheets_skipped'] !== []) {
            $this->warn('Sheets with no recognizable Model/Description/Price table (skipped): '.implode(', ', $stats['sheets_skipped']));
        }

        return self::SUCCESS;
    }
}
