<?php

namespace App\Http\Controllers;

use Fleetbase\Attributes\SkipAuthorizationCheck;
use Fleetbase\FleetOps\Http\Controllers\FleetOpsController;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Support\ProfileAccountManager;
use Fleetbase\Models\User;
use Fleetbase\Models\VerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * First-party driver credential actions. The package actions emailed/texted
 * the generated plaintext password; these issue a one-time reset code instead.
 */
class DriverCredentialController extends FleetOpsController
{
    protected array $methodPermissions = [
        'sendCredentials' => 'update-user-for driver',
        'resetCredentials' => 'update-user-for driver',
    ];

    #[SkipAuthorizationCheck]
    public function sendCredentials(string $id): JsonResponse
    {
        [$driver, $user, $error] = $this->resolveDriverLogin($id);
        if ($error) {
            return $error;
        }

        try {
            $sentVia = $this->sendResetCode($driver, $user);
        } catch (Throwable $exception) {
            Log::warning('Driver setup code delivery failed.', ['driver_uuid' => $driver->uuid, 'exception' => get_class($exception)]);
            return response()->json(['message' => 'A one-time reset code could not be sent. Check the driver contact details and mail or SMS provider.'], 422);
        }

        if ($user->status !== 'active') {
            $user->activate();
        }

        return response()->json([
            'status' => 'ok',
            'sent_via' => $sentVia,
            'message' => 'A one-time password setup code was sent. No password was included in the message.',
            'driver' => $this->driverLoginPayload($driver, $user),
        ]);
    }

    #[SkipAuthorizationCheck]
    public function resetCredentials(Request $request, string $id): JsonResponse
    {
        $password = $request->input('password');
        if (!is_string($password) || strlen($password) < 8) {
            return response()->json(['message' => 'Password must be at least 8 characters.'], 422);
        }

        if ($password !== $request->input('password_confirmation')) {
            return response()->json(['message' => 'Passwords do not match.'], 422);
        }

        [$driver, $user, $error] = $this->resolveDriverLogin($id);
        if ($error) {
            return $error;
        }

        $sentVia = null;
        if ($request->boolean('send_credentials')) {
            try {
                $sentVia = $this->sendResetCode($driver, $user);
            } catch (Throwable $exception) {
                Log::warning('Driver setup code delivery failed during an admin password reset.', ['driver_uuid' => $driver->uuid, 'exception' => get_class($exception)]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'The password was not changed because a one-time setup code could not be sent. Check the driver contact details and mail or SMS provider.',
                ], 422);
            }
        }

        // Do not change the current password until the requested setup/reset
        // code has been created and queued successfully; otherwise a provider
        // failure can leave the driver locked out with no recovery code.
        $user->changePassword($password);

        return response()->json([
            'status' => 'ok',
            'sent_via' => $sentVia,
            'message' => $sentVia
                ? 'A one-time password setup code was sent. No password was included in the message.'
                : 'Driver password updated. The driver can use Forgot password to set a new password with a one-time code.',
            'driver' => $this->driverLoginPayload($driver, $user),
        ]);
    }

    /** @return array{0: Driver|null, 1: User|null, 2: JsonResponse|null} */
    protected function resolveDriverLogin(string $id): array
    {
        $driver = Driver::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($query) => $query->where('uuid', $id)->orWhere('public_id', $id))
            ->first();

        if (!$driver) {
            return [null, null, response()->json(['message' => 'Driver not found.'], 404)];
        }

        $user = $driver->user_uuid ? User::where('uuid', $driver->user_uuid)->first() : null;
        if (!$user) {
            return [$driver, null, response()->json(['message' => 'This driver has no login account.'], 422)];
        }

        if (ProfileAccountManager::isStaffAccount($user)) {
            return [$driver, $user, response()->json(['message' => 'This driver signs in with a team member account. Manage this login in IAM.'], 422)];
        }

        if (ProfileAccountManager::isSharedWithAnotherCompany($user, $driver->company_uuid)) {
            return [$driver, $user, response()->json(['message' => ProfileAccountManager::SHARED_LOGIN_MESSAGE], 422)];
        }

        return [$driver, $user, null];
    }

    protected function sendResetCode(Driver $driver, User $user): string
    {
        $identity = $user->email ?: $user->phone;
        if (!$identity) {
            throw new \RuntimeException('Driver account has no verified email address or phone number.');
        }

        $options = [
            'company_uuid' => $driver->company_uuid,
            'meta' => ['identity' => $identity],
            'subject' => config('app.name') . ' password setup code',
            'messageCallback' => fn ($verification) => 'Your ' . config('app.name') . ' password setup code is ' . $verification->code,
        ];

        if ($user->email) {
            VerificationCode::generateEmailVerificationFor($user, 'driver_password_reset', $options);
            return 'email';
        }

        $options['hashed'] = true;
        VerificationCode::generateSmsVerificationFor($user, 'driver_password_reset', $options);
        return 'sms';
    }

    private function driverLoginPayload(Driver $driver, User $user): array
    {
        return [
            'id' => $driver->public_id,
            'uuid' => $driver->uuid,
            'user_uuid' => $user->uuid,
            'login_status' => $user->status,
            'user' => [
                'id' => $user->public_id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
        ];
    }
}
