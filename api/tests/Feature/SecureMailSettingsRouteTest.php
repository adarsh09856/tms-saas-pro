<?php

namespace Tests\Feature;

use App\Http\Controllers\SecureMailSettingsController;
use App\Http\Controllers\StaffCredentialController;
use App\Services\SecureMailSettings;
use Fleetbase\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    public function test_secure_mail_settings_preserve_all_supported_provider_choices(): void
    {
        $providers = ['smtp', 'microsoft-graph', 'mailgun', 'postmark', 'sendgrid'];
        $settings = new SecureMailSettings();

        $this->assertEqualsCanonicalizing($providers, array_intersect($providers, $settings->availableMailers()));
        $this->assertNotContains('ses', $settings->availableMailers());
        $this->assertNotContains('sendmail', $settings->availableMailers());
        $this->assertNotContains('array', $settings->availableMailers());
        $this->assertNotContains('resend', $settings->availableMailers());
    }

    public function test_registered_mail_transports_resolve_without_sending(): void
    {
        config([
            'mail.mailers.microsoft-graph.from.address' => 'admin@example.test',
            'mail.mailers.microsoft-graph.tenant_id' => 'test-tenant',
            'mail.mailers.microsoft-graph.client_id' => 'test-client',
            'mail.mailers.microsoft-graph.client_secret' => 'test-secret',
            'services.mailgun.domain' => 'example.test',
            'services.mailgun.secret' => 'test-secret',
            'services.postmark.token' => 'test-token',
            'services.sendgrid.api_key' => 'test-key',
            'services.resend.key' => 're_test-key',
        ]);
        $manager = app('mail.manager');
        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $manager->mailer('smtp'));
        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $manager->mailer('microsoft-graph'));
        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $manager->mailer('mailgun'));
        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $manager->mailer('postmark'));
        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $manager->mailer('sendgrid'));
    }

    public function test_save_mail_config_accepts_each_supported_provider(): void
    {
        $service = $this->mock(SecureMailSettings::class);
        $service->shouldReceive('availableMailers')->andReturn(['smtp', 'microsoft-graph', 'mailgun', 'postmark', 'sendgrid']);
        $service->shouldReceive('save')->times(5);
        $controller = new SecureMailSettingsController($service);
        $providers = ['smtp', 'microsoft-graph', 'mailgun', 'postmark', 'sendgrid'];

        foreach ($providers as $provider) {
            $request = \Fleetbase\Http\Requests\AdminRequest::create('/mail-config', 'POST', [
                'mailer' => $provider,
                'from' => ['address' => 'admin@example.test', 'name' => 'Admin'],
            ]);
            $request->setContainer($this->app);
            $request->setRedirector($this->app['redirect']);

            $response = $controller->update($request);
            $this->assertSame(200, $response->getStatusCode(), "Provider $provider should be accepted.");
        }
    }

    public function test_test_mail_config_accepts_each_supported_provider(): void
    {
        $providers = ['smtp', 'microsoft-graph', 'mailgun', 'postmark', 'sendgrid'];
        $user = new User();
        $user->forceFill(['uuid' => 'admin-user', 'email' => 'admin@example.test', 'name' => 'Admin']);
        $service = $this->mock(SecureMailSettings::class);
        $service->shouldReceive('availableMailers')->andReturn($providers);
        $service->shouldReceive('testConfig')->times(5)->andReturn([]);
        Mail::shouldReceive('purge')->times(10);
        Mail::shouldReceive('send')->times(5);
        $controller = new SecureMailSettingsController($service);

        foreach ($providers as $provider) {
            $request = \Fleetbase\Http\Requests\AdminRequest::create('/test-mail-config', 'POST', [
                'mailer' => $provider,
                'from' => ['address' => 'admin@example.test', 'name' => 'Admin'],
            ]);
            $request->setContainer($this->app);
            $request->setRedirector($this->app['redirect']);
            $request->setUserResolver(fn () => $user);

            $response = $controller->sendTestEmail($request);
            $this->assertSame(200, $response->getStatusCode(), "Provider $provider should be accepted.");
        }
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
