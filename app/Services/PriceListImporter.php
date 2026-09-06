<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PriceListItem;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Imports a vendor's own pricelist spreadsheet (real-world examples this
 * was built and hand-verified against: Hikvision/HiLook dealer pricelists,
 * Ruijie/Reyee runrate pricebooks — see docs/price-list-import.md) into
 * `price_list_items`, upserting on
 * (company_id, brand, sku) so re-running the same/an updated file just
 * refreshes prices rather than duplicating rows — this is the "update this
 * regularly" path, run from either `import:pricelist` or the Price List
 * resource's Import action in the admin panel.
 *
 * These spreadsheets are not one clean table: a single sheet often stacks
 * several product families end-to-end, each with its *own* header row (a
 * camera family's columns are Resolution/Lens/Material/…, an NVR family's
 * are Input Bandwidth/PoE Ports/…), sometimes interrupted by a one-cell
 * sub-category label row. The only columns that stay put across every
 * family are the ones this importer actually cares about: a model/SKU
 * column, a description column, and one-or-more price columns — so rather
 * than a fixed column mapping, each sheet is scanned row by row for a new
 * header whenever one appears.
 */
class PriceListImporter
{
    /**
     * @return array{rows_read: int, created: int, updated: int, sheets_skipped: array<int, string>}
     */
    public function import(string $filePath, Company $company, string $brand, ?string $sourceFilename = null): array
    {
        $stats = ['rows_read' => 0, 'created' => 0, 'updated' => 0, 'sheets_skipped' => []];
        $importedAt = Carbon::now();

        $reader = new Reader;
        $reader->open($filePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = $sheet->getName();
            $headerMap = null; // ['model' => colIndex, 'description' => ?colIndex, 'prices' => [label => colIndex]]
            $category = $sheetName;
            $sheetHadData = false;

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(
                    fn ($value) => is_string($value) ? trim($value) : $value,
                    $row->toArray()
                );

                if ($this->isBlankRow($values)) {
                    continue;
                }

                if ($detected = $this->detectHeader($values)) {
                    $headerMap = $detected;
                    $category = $sheetName;

                    continue;
                }

                if ($headerMap === null) {
                    continue;
                }

                if ($this->isCategoryLabelRow($values)) {
                    $category = trim((string) $this->firstNonBlank($values));

                    continue;
                }

                $sku = $values[$headerMap['model']] ?? null;

                if (blank($sku) || ! is_string($sku)) {
                    continue;
                }

                $description = $headerMap['description'] !== null
                    ? ($values[$headerMap['description']] ?? null)
                    : null;

                $prices = [];

                foreach ($headerMap['prices'] as $label => $col) {
                    $value = $values[$col] ?? null;

                    if (is_numeric($value)) {
                        $prices[$label] = round((float) $value, 4);
                    }
                }

                if ($prices === []) {
                    // A row with a model but no numeric price at all is
                    // almost always a mis-detected label/footnote row, not
                    // real catalog data — skip rather than import garbage.
                    continue;
                }

                $stats['rows_read']++;
                $sheetHadData = true;

                $item = PriceListItem::query()->updateOrCreate(
                    ['company_id' => $company->id, 'brand' => $brand, 'sku' => $sku],
                    [
                        'category' => $category,
                        'description' => $description ?: null,
                        'prices' => $prices,
                        'reference_price' => $this->pickReferencePrice($prices),
                        'source_file' => $sourceFilename,
                        'imported_at' => $importedAt,
                    ]
                );

                $item->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            }

            if (! $sheetHadData) {
                $stats['sheets_skipped'][] = $sheetName;
            }
        }

        $reader->close();

        return $stats;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A category/section label row: exactly one non-blank cell, and it
     * isn't itself a header row (already ruled out by the caller).
     *
     * @param  array<int, mixed>  $values
     */
    private function isCategoryLabelRow(array $values): bool
    {
        $nonBlank = array_filter($values, fn ($value) => filled($value));

        return count($nonBlank) === 1 && is_string(reset($nonBlank));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstNonBlank(array $values): mixed
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array{model: int, description: ?int, prices: array<string, int>}|null
     */
    private function detectHeader(array $values): ?array
    {
        $modelCol = null;
        $descriptionCol = null;
        $priceCols = [];

        foreach ($values as $col => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $normalized = strtolower(trim($value));

            if ($modelCol === null && in_array($normalized, ['model', 'basic model', 'product name'], true)) {
                $modelCol = $col;
            } elseif ($descriptionCol === null && in_array($normalized, ['description', 'descriptions'], true)) {
                $descriptionCol = $col;
            } elseif (
                ! str_contains($normalized, 'service')
                && (str_contains($normalized, 'price') || str_contains($normalized, 'msrp'))
            ) {
                // "contains msrp"/"contains price", not an exact match —
                // real headers dress the tier up with extra qualifiers
                // ("MSRP Inc. PPN", "Bottom Dealer Price(IDR) Exc PPN").
                // Excludes anything with "service" in the label (Ruijie's
                // "Service Products Price ... (USD per Year)" is a support/
                // renewal add-on priced in a different currency and unit
                // entirely — not a per-unit price tier for this SKU).
                $priceCols[trim($value)] = $col;
            }
        }

        if ($modelCol === null || $priceCols === []) {
            return null;
        }

        // Some vendor sheets never label the description column at all —
        // seen in the Ruijie/Reyee "SMB Pricebook" sheet, across two real
        // file revisions (one leaves the header cell blank, the other has
        // a stray unrelated value in it) — but the column is consistently
        // the one right after Model, and really does hold description
        // text in every data row. Only used when no column was explicitly
        // labeled Description/Descriptions.
        if ($descriptionCol === null && ! in_array($modelCol + 1, $priceCols, true)) {
            $candidate = $modelCol + 1;

            if (array_key_exists($candidate, $values)) {
                $descriptionCol = $candidate;
            }
        }

        return ['model' => $modelCol, 'description' => $descriptionCol, 'prices' => $priceCols];
    }

    /**
     * Prefers the dealer's own cost tier (however the vendor happens to
     * label it this file: "Dealer price", "Installer price", "to DPP", …)
     * over a public/MSRP tier, since that's the more useful default for a
     * reseller pricing their own products from this catalog — falls back
     * to MSRP, then whatever's first, rather than leaving it blank.
     *
     * @param  array<string, float>  $prices
     */
    private function pickReferencePrice(array $prices): ?float
    {
        foreach ($prices as $label => $amount) {
            $normalized = strtolower($label);

            if (str_contains($normalized, 'dealer') || str_contains($normalized, 'installer')) {
                return $amount;
            }

            if (str_contains($normalized, 'dpp') && ! str_contains($normalized, 'non')) {
                return $amount;
            }
        }

        foreach ($prices as $label => $amount) {
            if (str_contains(strtolower($label), 'msrp')) {
                return $amount;
            }
        }

        return $prices === [] ? null : reset($prices);
    }
}
