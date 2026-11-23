<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Where to send an already-authenticated request that hits a guest-only route.
     *
     * App\Http\Middleware\RedirectIfAuthenticated (the `guest` alias in
     * App\Http\Kernel) redirects to this constant. It was dropped when the stock
     * Laravel provider was replaced, so the alias would fatal with "Undefined
     * constant" the moment any route actually used it. No route does today,
     * which is why nothing caught it — the console is served separately, so the
     * only sensible in-app target is the API root.
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(
            function () {
                $internalPrefix = trim((string) config('fleetbase.api.routing.internal_prefix', 'int'), '/');

                // Ownership-claim APIs only; tenant context remains the existing authenticated
                // company/session context until a separate hostname-routing integration is reviewed.
                Route::prefix($internalPrefix . '/v1/platform/vendor-domains')
                    ->middleware([
                        'api',
                        'fleetbase.protected',
                        \Fleetbase\Http\Middleware\AdminGuard::class,
                        'throttle:60,1',
                    ])
                    ->group(function () {
                        Route::get('/', [\App\Http\Controllers\VendorDomainController::class, 'index']);
                        Route::post('/', [\App\Http\Controllers\VendorDomainController::class, 'store']);
                        Route::get('/{id}', [\App\Http\Controllers\VendorDomainController::class, 'show'])->whereNumber('id');
                        Route::post('/{id}/verify', [\App\Http\Controllers\VendorDomainController::class, 'verify'])->whereNumber('id');
                        Route::patch('/{id}/status', [\App\Http\Controllers\VendorDomainController::class, 'updateStatus'])->whereNumber('id');
                        Route::delete('/{id}', [\App\Http\Controllers\VendorDomainController::class, 'destroy'])->whereNumber('id');
                    });

                Route::prefix($internalPrefix . '/v1/vendor/domains')
                    ->middleware(['api', 'fleetbase.protected', 'throttle:60,1'])
                    ->group(function () {
                        Route::get('/', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'index']);
                        Route::post('/', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'store']);
                        Route::get('/{id}', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'show'])->whereNumber('id');
                        Route::post('/{id}/verify', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'verify'])->whereNumber('id');
                        Route::patch('/{id}/status', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'updateStatus'])->whereNumber('id');
                        Route::delete('/{id}', [\App\Http\Controllers\VendorSelfServiceDomainController::class, 'destroy'])->whereNumber('id');
                    });

                Route::get(
                    '/health',
                    function (Request $request) {
                        return response()->json(
                            [
                                'status' => 'ok',
                                'time' => microtime(true) - $request->attributes->get('request_start_time')
                            ]
                        );
                    }
                );
            }
        );
    }
}
