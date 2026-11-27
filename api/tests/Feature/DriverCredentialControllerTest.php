<?php

namespace Tests\Feature;

use App\Http\Controllers\DriverCredentialController;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\Models\User;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class DriverCredentialControllerTest extends TestCase
{
    public function test_password_is_not_changed_when_setup_code_delivery_fails(): void
    {
        $driver = new Driver();
        $driver->setRawAttributes(['uuid' => 'driver-uuid']);

        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldNotReceive('changePassword');

        $controller = new class($driver, $user) extends DriverCredentialController {
            public function __construct(private Driver $testDriver, private User $testUser)
            {
                parent::__construct();
            }

            protected function resolveDriverLogin(string $id): array
            {
                return [$this->testDriver, $this->testUser, null];
            }

            protected function sendResetCode(Driver $driver, User $user): string
            {
                throw new RuntimeException('Mail provider unavailable.');
            }
        };

        $response = $controller->resetCredentials(Request::create('/int/v1/drivers/driver-123/reset-credentials', 'POST', [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'send_credentials' => true,
        ]), 'driver-123');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'The password was not changed because a one-time setup code could not be sent. Check the driver contact details and mail or SMS provider.',
            $response->getData(true)['message']
        );
    }
}
