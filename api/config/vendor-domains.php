<?php

return [
    // Explicitly reserve every first-party hostname used by the platform.
    // Subdomains can be offered only below the separately configured base.
    'reserved_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('PLATFORM_RESERVED_HOSTS', ''))
    ))),
    'subdomain_base_domain' => env('VENDOR_SUBDOMAIN_BASE_DOMAIN'),
    'reserved_subdomain_labels' => array_values(array_filter(array_map(
        static fn (string $label): string => strtolower(trim($label)),
        explode(',', (string) env('VENDOR_RESERVED_SUBDOMAIN_LABELS', 'tms,admin,api,www'))
    ))),
    'verification_ttl_minutes' => (int) env('VENDOR_DOMAIN_VERIFICATION_TTL_MINUTES', 1440),
];
