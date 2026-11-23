<?php

namespace App\Services;

class VendorDomainDnsTxtLookup
{
    /** @return array<int, string> */
    public function recordsFor(string $hostname): array
    {
        if (!function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($hostname, DNS_TXT);
        if (!is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => isset($record['txt']) ? (string) $record['txt'] : (isset($record['entries']) ? implode('', (array) $record['entries']) : null),
            $records
        )));
    }
}
