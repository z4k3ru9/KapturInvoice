<?php

namespace App\Support\Homepage;

use App\Models\Company;

/**
 * Curated marketing copy for the public homepage's "portfolio" layout (the
 * Google Stitch "TALL IT Services Portfolio" design — see
 * resources/views/livewire/home-page.blade.php). Keyed by Company slug rather than stored on the
 * model/settings table: this is bespoke copy for the two known real
 * entities (both Surabaya IT/security-infrastructure integrators — the
 * "services"/"partners" lists below are drawn from their actual historical
 * InvoiceNinja product catalogs, not invented), not a generic CMS field a
 * tenant is expected to self-edit. `default()` keeps the page renderable
 * (with generic-but-honest copy) for any company added later without one.
 */
class PortfolioContent
{
    public static function for(Company $company): array
    {
        return match ($company->slug) {
            'company-a' => self::companyA(),
            'company-b' => self::companyB(),
            default => self::default($company),
        };
    }

    private static function companyA(): array
    {
        return [
            'eyebrow' => 'SECURITY • IT • HARDWARE SERVICES',
            'headline_lead' => 'RELIABLE SECURITY',
            'headline_tail' => '& IT SERVICES',
            'subheadline' => 'We design and install CCTV and security systems, provide practical IT consulting, and keep your computer hardware running smoothly.',
            'tagline' => 'Professional security surveillance, IT consulting, and hardware maintenance',
            'ticker' => [
                'Field engineers based in Surabaya',
                'CAT6A • cabling & structured networking',
                'Same-day response on service calls',
            ],
            'partners' => [
                ['name' => 'HIKVISION', 'tag' => 'CCTV & DVR/NVR'],
                ['name' => 'MIKROTIK', 'tag' => 'Routers & Networking'],
                ['name' => 'TP-LINK', 'tag' => 'Switches'],
                ['name' => 'SYNOLOGY', 'tag' => 'NAS Storage'],
                ['name' => 'SEAGATE', 'tag' => 'Storage Drives'],
            ],
            'process' => [
                ['title' => 'ASSESS & DESIGN', 'body' => 'On-site survey and a tailored CCTV, cabling, and network layout for your premises.'],
                ['title' => 'INSTALL & CONFIGURE', 'body' => 'Camera and DVR/NVR installation, structured cabling, switch/router setup and tuning.'],
                ['title' => 'MAINTAIN & SUPPORT', 'body' => 'Routine servicing, hardware repairs, and fast on-site response to keep systems running.'],
            ],
            'services' => [
                [
                    'title' => 'SECURITY SURVEILLANCE',
                    'tag' => 'CCTV & SURVEILLANCE',
                    'body' => 'Design, installation, and maintenance of CCTV camera systems and video storage for homes and businesses.',
                    'foot' => 'CCTV CAMERAS • DVR/NVR • VIDEO STORAGE',
                ],
                [
                    'title' => 'NETWORKING & CABLING',
                    'tag' => 'STRUCTURED CABLING',
                    'body' => 'Router and switch deployment, structured cabling, and crimping for reliable wired and wireless networks.',
                    'foot' => 'ROUTERS • SWITCHES • CABLE PULLING',
                ],
                [
                    'title' => 'HARDWARE & STORAGE',
                    'tag' => 'IT HARDWARE',
                    'body' => 'NAS and backup storage deployment, workstation setup, and hardware diagnostics/repair.',
                    'foot' => 'NAS STORAGE • WORKSTATIONS • REPAIRS',
                ],
            ],
            'sla_title' => 'ON-SITE SUPPORT IN SURABAYA',
            'sla_body' => 'Genuine hardware, installed and serviced by our own field engineers.',
        ];
    }

    private static function companyB(): array
    {
        return [
            'eyebrow' => 'NETWORKING • SURVEILLANCE • IT INFRASTRUCTURE',
            'headline_lead' => 'ENTERPRISE NETWORK',
            'headline_tail' => '& IT INFRASTRUCTURE',
            'subheadline' => 'We plan, deploy, and support business networking, surveillance, and IT hardware — from core routing to the workstations on every desk.',
            'tagline' => 'Networking, surveillance, and IT hardware for growing businesses',
            'ticker' => [
                'Field engineers based in Surabaya',
                'Enterprise WiFi & PoE switching',
                'Same-day response on service calls',
            ],
            'partners' => [
                ['name' => 'MIKROTIK', 'tag' => 'Routing'],
                ['name' => 'RUIJIE', 'tag' => 'Switching & WiFi'],
                ['name' => 'HIKVISION', 'tag' => 'Surveillance'],
                ['name' => 'LENOVO', 'tag' => 'Workstations & Servers'],
                ['name' => 'YEASTAR', 'tag' => 'IP Telephony'],
            ],
            'process' => [
                ['title' => 'ASSESS & DESIGN', 'body' => 'Site survey and network topology planning across routing, WiFi, and surveillance coverage.'],
                ['title' => 'DEPLOY & CONFIGURE', 'body' => 'Router, switch, and access point rollout; camera/NVR commissioning; workstation setup.'],
                ['title' => 'SUPPORT & MAINTAIN', 'body' => 'Ongoing hardware support and fast response to keep your network and systems online.'],
            ],
            'services' => [
                [
                    'title' => 'NETWORK INFRASTRUCTURE',
                    'tag' => 'ROUTING & WIFI',
                    'body' => 'Mikrotik and Ruijie router, PoE switch, and enterprise WiFi deployment for offices of any size.',
                    'foot' => 'ROUTERS • POE SWITCHES • WIFI 6',
                ],
                [
                    'title' => 'SURVEILLANCE & SECURITY',
                    'tag' => 'CCTV & NVR',
                    'body' => 'HIKVISION camera and NVR systems designed and installed for reliable round-the-clock coverage.',
                    'foot' => 'IP CAMERAS • NVR • REMOTE VIEWING',
                ],
                [
                    'title' => 'IT HARDWARE & TELEPHONY',
                    'tag' => 'WORKSTATIONS & PBX',
                    'body' => 'Lenovo workstation and server rollout, plus Yeastar IP PBX setup for office telephony.',
                    'foot' => 'WORKSTATIONS • SERVERS • IP PBX',
                ],
            ],
            'sla_title' => 'ON-SITE SUPPORT IN SURABAYA',
            'sla_body' => 'Genuine OEM hardware, backed by our own field engineers.',
        ];
    }

    /**
     * Fallback for a company with no bespoke copy yet — generic but honest
     * (no invented partner/service claims), keyed only off fields already
     * on Company so the homepage never 500s for a newly seeded tenant.
     */
    private static function default(Company $company): array
    {
        return [
            'eyebrow' => 'IT SERVICES',
            'headline_lead' => strtoupper($company->name),
            'headline_tail' => '',
            'subheadline' => 'Get in touch to find out how we can help.',
            'tagline' => $company->name,
            'ticker' => [],
            'partners' => [],
            'process' => [],
            'services' => [],
            'sla_title' => 'GET IN TOUCH',
            'sla_body' => 'Send us a message and we\'ll get back to you.',
        ];
    }
}
