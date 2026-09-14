import { KARUNIA_HOST, AXEN_HOST, BASE_URL } from '../../../playwright.config';

/**
 * Seeded company identity shared by browser specs — see
 * database/seeders/CompanySeeder.php and CLAUDE.md "Login / seeded data".
 * Admin panel login is the same for every company (shared user, tenant
 * switch by URL path); the public homepage/portal are Host-header scoped.
 */
export const ADMIN_EMAIL = 'test@example.com';
export const ADMIN_PASSWORD = 'password';

export const COMPANIES = {
    karunia: {
        slug: 'karunia-abadi',
        host: KARUNIA_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', KARUNIA_HOST),
        adminUrl: `${BASE_URL}/admin/karunia-abadi`,
    },
    axen: {
        slug: 'axen-technology-indonesia',
        host: AXEN_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', AXEN_HOST),
        adminUrl: `${BASE_URL}/admin/axen-technology-indonesia`,
    },
} as const;

export type CompanyKey = keyof typeof COMPANIES;
