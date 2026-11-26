<?php

namespace App\Http\Middleware;

use Closure;
use Fleetbase\Models\Company;
use Fleetbase\Models\CompanyUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Select the vendor tenant from a verified request hostname, after Fleetbase
 * has authenticated the user and initialized its session, but before any
 * permission or billing middleware reads the tenant context.
 */
class ResolveVendorDomainTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        if ($host === '' || in_array($host, $this->platformHosts(), true)) {
            return $next($request);
        }

        // An unknown hostname must never inherit the account's default company.
        $claim = \App\Models\VendorDomainClaim::query()
            ->where('host', $host)
            ->first();
        if (!$claim) {
            return response()->json(['message' => 'This vendor workspace hostname is not provisioned.'], 404);
        }

        if ($claim->status !== 'verified' || $claim->verified_at === null) {
            return response()->json(['message' => 'This vendor workspace hostname is not active.'], 404);
        }

        $company = Company::query()
            ->where('uuid', $claim->company_uuid)
            ->where('status', 'active')
            ->first();
        if (!$company) {
            return response()->json(['message' => 'This vendor workspace is unavailable.'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentication is required for this vendor workspace.'], 401);
        }
        if (!$request->hasSession()) {
            return response()->json(['message' => 'A company session is required for this vendor workspace.'], 401);
        }

        $membership = CompanyUser::query()
            ->where('company_uuid', $company->uuid)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'You do not have access to this vendor workspace.'], 403);
        }

        $request->session()->put('company', $company->uuid);
        // Fleetbase permission helpers read the in-memory user's companyUser
        // relation as well as session('company'). Align both for this request
        // without persisting a change to the user's default company.
        $user->setAttribute('company_uuid', $company->uuid);
        $user->setRelation('company', $company);
        $user->setRelation('companyUser', $membership);
        $request->attributes->set('vendor_domain_company_uuid', $company->uuid);

        return $next($request);
    }

    /** @return array<int, string> */
    private function platformHosts(): array
    {
        $configured = [
            $this->hostOnly((string) config('app.url', '')),
            $this->hostOnly((string) config('fleetbase.console.host', '')),
            (string) config('vendor-domains.subdomain_base_domain', ''),
            ...((array) config('vendor-domains.reserved_hosts', [])),
        ];

        return array_values(array_unique(array_filter(array_map(
            static fn ($candidate): string => strtolower(rtrim(trim((string) $candidate), '.')),
            $configured
        ))));
    }

    private function hostOnly(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parsed = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST);
        return is_string($parsed) ? $parsed : '';
    }
}
