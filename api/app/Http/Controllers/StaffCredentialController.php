<?php

namespace App\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Requests\ChangePasswordRequest;
use Fleetbase\Models\User;
use Fleetbase\Models\VerificationCode;
use Fleetbase\Notifications\UserForgotPassword;
use Fleetbase\Support\Auth;
use Fleetbase\Support\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Staff password changes never mail the newly assigned plaintext password. */
class StaffCredentialController extends Controller
{
    public function changeUserPassword(ChangePasswordRequest $request): JsonResponse
    {
        $actingUser = Auth::getUserFromSession($request);
        if (!$actingUser || !($actingUser->isAdmin() || $actingUser->hasRole('Administrator') || $actingUser->hasPermissionTo('iam change-password-for user'))) {
            return response()->json(['message' => 'Not authorized to change user password.'], 401);
        }

        $userId = $request->input('user');
        if (!$userId) {
            return response()->json(['message' => 'No user specified to change password for.'], 422);
        }

        $targetUser = User::where('uuid', $userId)
            ->whereHas('anyCompanyUser', fn ($query) => $query->where('company_uuid', session('company')))
            ->first();
        if (!$targetUser) {
            return response()->json(['message' => 'User not found to change password for.'], 404);
        }

        $sendReset = $request->boolean('send_credentials');
        if ($sendReset && !$targetUser->email) {
            return response()->json(['message' => 'This staff account has no email address for a secure password reset.'], 422);
        }

        $targetUser->changePassword($request->input('password'));
        if (!$sendReset) {
            return response()->json(['status' => 'ok', 'message' => 'User password updated.']);
        }

        try {
            $this->sendResetLink($targetUser);
        } catch (Throwable $exception) {
            Log::warning('Staff password reset message could not be queued after an admin password update.', [
                'user_uuid' => $targetUser->uuid,
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'The password was changed, but a secure reset email could not be queued. The user can request a reset from the sign-in page.',
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'sent_via' => 'email',
            'message' => 'A one-time password reset link was queued. The new password was not emailed.',
        ]);
    }

    private function sendResetLink(User $user): void
    {
        VerificationCode::where('subject_uuid', $user->uuid)
            ->where('for', 'password_reset')
            ->where('status', 'active')
            ->delete();

        $verificationCode = VerificationCode::create([
            'subject_uuid' => $user->uuid,
            'subject_type' => Utils::getModelClassName($user),
            'for' => 'password_reset',
            'expires_at' => Carbon::now()->addMinutes(15),
            'meta' => ['email' => strtolower(trim((string) $user->email))],
            'status' => 'active',
        ]);

        $user->notify((new UserForgotPassword($verificationCode))->afterCommit());
    }
}
