<?php

namespace Tests\Feature;

use App\Http\Controllers\DriverCredentialController;
use App\Http\Middleware\BlockCustomerPortalCredentials;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerPortalCredentialGuardTest extends TestCase
{
    public function test_staff_customer_contact_create_and_update_remain_available(): void
    {
        foreach ([
            Request::create('/int/v1/contacts', 'POST', ['type' => 'customer', 'name' => 'Local customer']),
            Request::create('/int/v1/contacts/contact-123', 'PUT', ['name' => 'Updated local customer']),
        ] as $request) {
            $nextCalled = false;
            $response = app(BlockCustomerPortalCredentials::class)->handle($request, function () use (&$nextCalled) {
                $nextCalled = true;
                return response()->json(['ok' => true]);
            });

            $this->assertTrue($nextCalled);
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_customer_portal_welcome_and_credential_routes_are_disabled(): void
    {
        $middleware = app(BlockCustomerPortalCredentials::class);
        $nextCalled = false;
        $welcome = Request::create('/int/v1/contacts', 'POST', [
            'type' => 'customer',
            'meta' => ['customer_portal' => ['send_welcome_email' => true]],
        ]);
        $response = $middleware->handle($welcome, function () use (&$nextCalled) {
            $nextCalled = true;
            return response()->noContent();
        });

        $this->assertFalse($nextCalled);
        $this->assertSame(410, $response->getStatusCode());

        $customerCredentials = Request::create('/int/v1/customers/customer-123/send-credentials', 'POST');
        $this->assertSame(410, $middleware->handle($customerCredentials, fn () => response()->noContent())->getStatusCode());
    }

    public function test_order_creation_and_driver_reset_workflows_are_not_blocked(): void
    {
        $middleware = app(BlockCustomerPortalCredentials::class);

        foreach ([
            Request::create('/int/v1/orders', 'POST'),
            Request::create('/v1/drivers/forgot-password', 'POST', ['identity' => 'driver@example.test']),
            Request::create('/int/v1/drivers/driver-123/send-credentials', 'POST'),
        ] as $request) {
            $nextCalled = false;
            $middleware->handle($request, function () use (&$nextCalled) {
                $nextCalled = true;
                return response()->noContent();
            });
            $this->assertTrue($nextCalled);
        }
    }

    public function test_staff_contact_order_and_driver_routes_remain_registered(): void
    {
        foreach ([
            ['/int/v1/contacts', 'POST'],
            ['/int/v1/orders', 'POST'],
            ['/int/v1/drivers/driver-123/send-credentials', 'POST'],
        ] as [$uri, $method]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->assertNotSame('App\\Http\\Controllers\\SecureMailSettingsController', $route->getControllerClass());
            $this->assertContains('fleetbase.protected', $route->gatherMiddleware());
        }

        $kernel = app(\App\Http\Kernel::class);
        $groups = (new \ReflectionProperty($kernel, 'middlewareGroups'))->getValue($kernel);
        $this->assertContains(BlockCustomerPortalCredentials::class, $groups['api']);

        $sendCredentials = app('router')->getRoutes()->match(Request::create('/int/v1/drivers/driver-123/send-credentials', 'POST'));
        $this->assertSame(DriverCredentialController::class . '@sendCredentials', $sendCredentials->getActionName());

        $resetCredentials = app('router')->getRoutes()->match(Request::create('/int/v1/drivers/driver-123/reset-credentials', 'POST'));
        $this->assertSame(DriverCredentialController::class . '@resetCredentials', $resetCredentials->getActionName());
    }

    public function test_driver_credential_email_uses_the_one_time_reset_flow_without_rendering_password(): void
    {
        $this->assertTrue(view()->exists('fleetops::mail.driver-credentials'));
        $rendered = view('fleetops::mail.driver-credentials', [
            'user' => (object) ['name' => 'Test driver'],
            'companyName' => 'Test Transport Company',
            'identity' => 'driver@example.test',
            'plaintextPassword' => 'must-never-appear-in-email',
            'driver' => (object) [],
        ])->render();

        $this->assertStringContainsString('Forgot password', $rendered);
        $this->assertStringContainsString('one-time code', $rendered);
        $this->assertStringNotContainsString('must-never-appear-in-email', $rendered);
    }

    public function test_staff_credential_email_never_renders_the_plaintext_password(): void
    {
        $this->assertTrue(view()->exists('fleetbase::mail.user-credentials'));
        $rendered = view('fleetbase::mail.user-credentials', [
            'user' => (object) ['name' => 'Test staff'],
            'plaintextPassword' => 'must-never-appear-in-email',
            'currentHour' => 10,
        ])->render();

        $this->assertStringContainsString('Forgot password', $rendered);
        $this->assertStringNotContainsString('must-never-appear-in-email', $rendered);
    }

    public function test_customer_credential_mail_cannot_expose_a_portal_or_password(): void
    {
        $this->assertTrue(view()->exists('fleetops::mail.customer-credentials'));
        $rendered = view('fleetops::mail.customer-credentials', [
            'customer' => (object) ['name' => 'Test customer'],
            'plaintextPassword' => 'must-never-appear-in-email',
        ])->render();

        $this->assertStringContainsString('does not offer customer accounts or a customer portal', $rendered);
        $this->assertStringNotContainsString('must-never-appear-in-email', $rendered);
    }
}
