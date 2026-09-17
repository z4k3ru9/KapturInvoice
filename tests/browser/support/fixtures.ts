import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Reads the deterministic per-company fixture manifest written by
 * database/seeders/PlaywrightFixturesSeeder.php (storage/app/
 * playwright-fixtures.json) — client/invoice ids and portal link keys
 * that only exist after that seeder has run against the dedicated
 * database/testing-browser.sqlite (see scripts/browser-test-server.sh).
 */
export interface CompanyFixture {
    client_id: number;
    other_client_id: number;
    invoice_id_indonesian: number;
    invoice_number_indonesian: string;
    invoice_id_english: number;
    invoice_number_english: string;
    draft_invoice_id: number;
    draft_invoice_id_for_conflict_test: number;
    active_portal_link_key: string;
    expired_portal_link_key: string;
    ordinary_active_portal_link_key: string;
    replaced_portal_link_key: string;
}

export interface FixtureManifest {
    'company-a': CompanyFixture;
    'company-b': CompanyFixture;
}

export function loadFixtures(): FixtureManifest {
    const manifestPath = path.resolve(__dirname, '../../../storage/app/playwright-fixtures.json');

    return JSON.parse(readFileSync(manifestPath, 'utf-8'));
}
