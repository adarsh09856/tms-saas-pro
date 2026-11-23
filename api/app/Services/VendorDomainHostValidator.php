<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class VendorDomainHostValidator
{
    public function normalize(string $host, string $kind): string
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if (!in_array($kind, ['custom', 'platform_subdomain'], true)) {
            throw ValidationException::withMessages(['kind' => 'Choose custom or platform_subdomain.']);
        }

        if (
            $host === '' || strlen($host) > 253 ||
            filter_var($host, FILTER_VALIDATE_IP) !== false ||
            !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
        ) {
            throw ValidationException::withMessages(['host' => 'Enter a valid fully qualified DNS hostname.']);
        }

        $reserved = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            config('fleetbase.console.host'),
            ...((array) config('vendor-domains.reserved_hosts', [])),
        ]);
        $reserved = array_unique(array_map(static fn ($value) => strtolower(rtrim((string) $value, '.')), $reserved));
        $base = strtolower(rtrim((string) config('vendor-domains.subdomain_base_domain', ''), '.'));

        if ($kind === 'platform_subdomain') {
            if ($base === '' || !str_ends_with($host, '.' . $base)) {
                throw ValidationException::withMessages(['host' => 'This hostname is not under the configured vendor subdomain base.']);
            }

            $prefix = substr($host, 0, -strlen('.' . $base));
            if ($prefix === '' || str_contains($prefix, '.')) {
                throw ValidationException::withMessages(['host' => 'Use one vendor label directly beneath the configured subdomain base.']);
            }
            if (in_array($prefix, (array) config('vendor-domains.reserved_subdomain_labels', []), true)) {
                throw ValidationException::withMessages(['host' => 'This subdomain label is reserved by the platform.']);
            }
        } else {
            foreach ($reserved as $reservedHost) {
                if ($host === $reservedHost || str_ends_with($host, '.' . $reservedHost)) {
                    throw ValidationException::withMessages(['host' => 'A platform hostname cannot be claimed as a custom domain.']);
                }
            }
        }

        return $host;
    }
}
