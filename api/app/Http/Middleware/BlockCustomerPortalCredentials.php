<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlockCustomerPortalCredentials
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/' . trim($request->path(), '/');
        $isContactWrite = preg_match('~/contacts(?:/[^/]+)?$~i', $path)
            && in_array($request->method(), ['POST', 'PUT', 'PATCH'], true);
        $requestsPortalWelcome = filter_var($request->input('meta.customer_portal.send_welcome_email'), FILTER_VALIDATE_BOOLEAN);

        if ($isContactWrite && $requestsPortalWelcome) {
            return response()->json([
                'message' => 'Customer portal accounts and welcome emails are not available. Customer contacts remain staff-managed.',
            ], 410);
        }

        // FleetOps customer credentials controllers are not registered in this
        // product configuration, but fail closed if a package adds these paths.
        if (Str::contains(strtolower($path), '/customers/') && preg_match('~/(?:send-credentials|reset-credentials|create-portal-login|deactivate-portal-login|reactivate-portal-login)$~i', $path)) {
            return response()->json([
                'message' => 'Customer portal login and credential flows are disabled. Customer contacts remain staff-managed.',
            ], 410);
        }

        return $next($request);
    }
}
