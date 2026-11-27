<?php

namespace App\Http\Controllers;

use App\Services\SecureMailSettings;
use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Requests\AdminRequest;
use Fleetbase\Mail\TestMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class SecureMailSettingsController extends Controller
{
    public function __construct(private SecureMailSettings $settings)
    {
    }

    public function show(AdminRequest $request)
    {
        return response()->json($this->settings->redactedConfig());
    }

    public function update(AdminRequest $request)
    {
        $validated = $request->validate([
            'mailer' => ['required', 'string', Rule::in($this->settings->availableMailers())],
            'from' => ['required', 'array'],
            'from.address' => ['required', 'email', 'max:254'],
            'from.name' => ['required', 'string', 'max:120'],
            'smtp' => ['sometimes', 'array'],
            'smtp.host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp.port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'smtp.username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp.password' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'smtp.encryption' => ['sometimes', 'nullable', 'in:tls,ssl'],
            'microsoftGraph' => ['sometimes', 'array'],
            'mailgun' => ['sometimes', 'array'],
            'postmark' => ['sometimes', 'array'],
            'sendgrid' => ['sometimes', 'array'],
            'resend' => ['sometimes', 'array'],
        ]);

        $this->settings->save($validated);

        return response()->json(['status' => 'OK']);
    }

    public function sendTestEmail(AdminRequest $request)
    {
        $validated = $request->validate([
            'mailer' => ['required', 'string', Rule::in($this->settings->availableMailers())],
            'from' => ['required', 'array'],
            'from.address' => ['required', 'email', 'max:254'],
            'from.name' => ['required', 'string', 'max:120'],
            'smtp' => ['sometimes', 'array'],
            'microsoftGraph' => ['sometimes', 'array'],
            'mailgun' => ['sometimes', 'array'],
            'postmark' => ['sometimes', 'array'],
            'sendgrid' => ['sometimes', 'array'],
            'resend' => ['sometimes', 'array'],
        ]);

        $originalConfig = ['mail' => config('mail')];
        foreach (['mailgun', 'postmark', 'sendgrid', 'resend'] as $provider) {
            $originalConfig['services.' . $provider] = config('services.' . $provider);
        }

        try {
            config($this->settings->testConfig($validated));
            Mail::purge($validated['mailer']);
            Mail::send(new TestMail($request->user(), $validated['mailer']));

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent. Check your inbox to confirm delivery.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Admin test email failed.', ['exception' => get_class($exception)]);

            return response()->json([
                'status' => 'error',
                'message' => 'The test email could not be sent. Check the mail configuration and provider status.',
            ], 422);
        } finally {
            config($originalConfig);
            Mail::purge($validated['mailer']);
        }
    }
}
