# Vendor price-list import

`App\Services\PriceListImporter` parses Hikvision/HiLook and Ruijie/Reyee spreadsheets into the reference `price_list_items` catalog. Use `php artisan import:pricelist {company} {file} --brand=` or the no-shell TallStackUI action at `/tall/{company:slug}/price-list-items`. Rows upsert on `(company_id, brand, sku)`; re-uploading a revision refreshes category, description, prices, reference price, source file, and timestamp. `ProductSync` creates or refreshes a real invoiceable Product and stores `products.price_list_item_id`.

The parser detects a new header per product family and carries a sheet/category label. It recognizes Model, Basic Model, Product name, Description/Descriptions, and price/MSRP columns while excluding service-price columns. It preserves vendor tier labels in the `prices` JSON map and chooses a reference price preferring dealer/installer/DPP, then MSRP, then the first numeric tier. Model rows with no numeric price are skipped. Description falls back to the column immediately after Model for Ruijie/Reyee files that leave it unlabeled.

Verified development files imported 34 HiLook rows, 364 Hikvision rows, and 117 then 120 Ruijie/Reyee rows; the later Ruijie/Reyee revision produced 11 creates and 109 updates. These files and imported commercial data must never be committed. Synthetic OpenSpout fixtures provide regression coverage.
