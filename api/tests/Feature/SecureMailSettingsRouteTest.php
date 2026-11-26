<?php

namespace Tests\Feature;

use App\Http\Controllers\SecureMailSettingsController;
use App\Http\Controllers\StaffCredentialController;
use App\Services\SecureMailSettings;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecureMailSettingsRouteTest extends TestCase
{
    public function test_secure_mail_routes_replace_package_handlers_and_remain_admin_protected(): void
    {
        $prefix = trim(implode('/', array_filter([
            trim((string) config('fleetbase.api.routing.prefix', ''), '/'),
            trim((string) config('fleetbase.api.routing.internal_prefix', 'int'), '/'),
            'v1',
            'settings',
        ])), '/');

        foreach (['GET', 'POST'] as $method) {
            $route = app('router')->getRoutes()->match(Request::create('/' . $prefix . '/mail-config', $method));
            $this->assertSame(SecureMailSettingsController::class . '@' . ($method === 'GET' ? 'show' : 'update'), $route->getActionName());
            $this->assertContains('fleetbase.protected', $route->gatherMiddleware());
        }

        $testRoute = app('router')->getRoutes()->match(Request::create('/' . $prefix . '/test-mail-config', 'POST'));
        $this->assertSame(SecureMailSettingsController::class . '@sendTestEmail', $testRoute->getActionName());
        $this->assertContains('fleetbase.protected', $testRoute->gatherMiddleware());
        $this->assertContains(\Fleetbase\Http\Middleware\ThrottleRequests::class, $testRoute->gatherMiddleware());
    }

    public function test_mail_secret_fields_are_stored_separately_from_provider_settings(): void
    {
        $this->assertArrayHasKey('smtpPassword', SecureMailSettings::SECRET_FIELDS);
        $this->assertSame('mail.mailers.smtp.password', SecureMailSettings::SECRET_FIELDS['smtpPassword']['config']);
        $this->assertSame('services.sendgrid.api_key', SecureMailSettings::SECRET_FIELDS['sendgridApi_key']['config']);
    }

    public function test_staff_password_change_uses_the_secure_reset_message_handler(): void
    {
        $prefix = trim(implode('/', array_filter([
            trim((string) config('fleetbase.api.routing.prefix', ''), '/'),
            trim((string) config('fleetbase.api.routing.internal_prefix', 'int'), '/'),
            'v1',
            'auth',
            'change-user-password',
        ])), '/');
        $route = app('router')->getRoutes()->match(Request::create('/' . $prefix, 'POST'));

        $this->assertSame(StaffCredentialController::class . '@changeUserPassword', ltrim($route->getActionName(), '\\'));
        $this->assertContains('fleetbase.protected', $route->gatherMiddleware());
    }
}
