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
        if (!$request->hasSession()) {
            return $next($request);
        }

        $host = strtolower(rtrim($request->getHost(), '.'));
        $centralHost = strtolower(rtrim((string) parse_url((string) config('app.url'), PHP_URL_HOST), '.'));
        $consoleHost = strtolower(rtrim((string) parse_url((string) config('fleetbase.console.host'), PHP_URL_HOST), '.'));

        if ($host === '' || $host === $centralHost || ($consoleHost !== '' && $host === $consoleHost)) {
            return $next($request);
        }

        $baseDomain = strtolower(rtrim((string) config('vendor-domains.subdomain_base_domain', ''), '.'));
        $isPlatformSubdomain = $baseDomain !== '' && str_ends_with($host, '.' . $baseDomain);

        // An unclaimed host below our vendor namespace must never fall back to
        // a user's default company workspace.
        $claim = \App\Models\VendorDomainClaim::query()->where('host', $host)->first();
        if (!$claim) {
            return $isPlatformSubdomain
                ? response()->json(['message' => 'This vendor workspace hostname is not provisioned.'], 404)
                : $next($request);
        }

        if ($claim->status !== 'verified') {
            return response()->json(['message' => 'This vendor workspace hostname is not active.'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $company = Company::query()->where('uuid', $claim->company_uuid)->first();
        if (!$company) {
            return response()->json(['message' => 'This vendor workspace is unavailable.'], 404);
        }

        $membership = CompanyUser::query()
            ->where('company_uuid', $company->uuid)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->exists();

        if (!$membership) {
            return response()->json(['message' => 'You do not have access to this vendor workspace.'], 403);
        }

        $request->session()->put('company', $company->uuid);
        $request->attributes->set('vendor_domain_company_uuid', $company->uuid);

        return $next($request);
    }
}
