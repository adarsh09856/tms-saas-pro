<?php

namespace App\Services;

use Fleetbase\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps provider credentials encrypted at rest and hydrates them into Laravel's
 * mail config only in the API/queue process. The Setting JSON values contain no
 * plaintext provider secrets after the first migration pass.
 */
class SecureMailSettings
{
    public const SECRET_SETTING = 'mail.encrypted_secrets';

    /** @var array<string, array{setting:string,field:string,config:string}> */
    public const SECRET_FIELDS = [
        'smtpPassword' => ['setting' => 'mail.mailers.smtp', 'field' => 'password', 'config' => 'mail.mailers.smtp.password'],
        'microsoftGraphClient_secret' => ['setting' => 'mail.mailers.microsoft-graph', 'field' => 'client_secret', 'config' => 'mail.mailers.microsoft-graph.client_secret'],
        'mailgunSecret' => ['setting' => 'services.mailgun', 'field' => 'secret', 'config' => 'services.mailgun.secret'],
        'postmarkToken' => ['setting' => 'services.postmark', 'field' => 'token', 'config' => 'services.postmark.token'],
        'sendgridApi_key' => ['setting' => 'services.sendgrid', 'field' => 'api_key', 'config' => 'services.sendgrid.api_key'],
        'resendKey' => ['setting' => 'services.resend', 'field' => 'key', 'config' => 'services.resend.key'],
    ];

    /** Migrate legacy plaintext credentials and make encrypted values available to mail transports. */
    public function hydrate(): void
    {
        if (Setting::doesntHaveConnection()) {
            return;
        }

        $secrets = (array) Setting::system(self::SECRET_SETTING, []);
        $rows = Setting::query()
            ->whereIn('key', collect(self::SECRET_FIELDS)->pluck('setting')->unique()->map(fn ($key) => 'system.' . $key))
            ->get(['key', 'value'])
            ->keyBy('key');

        foreach (self::SECRET_FIELDS as $definition) {
            $settingKey = 'system.' . $definition['setting'];
            $value = (array) data_get($rows->get($settingKey), 'value', []);
            $legacySecret = data_get($value, $definition['field']);

            if (is_string($legacySecret) && $legacySecret !== '' && empty($secrets[$definition['config']])) {
                try {
                    $secrets[$definition['config']] = Crypt::encryptString($legacySecret);
                    data_set($value, $definition['field'], null);
                    Setting::configure($settingKey, $value);
                } catch (Throwable $exception) {
                    data_set($value, $definition['field'], null);
                    Setting::configure($settingKey, $value);
                    Log::error('Unable to encrypt a legacy mail credential; it was cleared from legacy storage and must be re-entered by an admin.', [
                        'setting' => $definition['setting'],
                        'exception' => get_class($exception),
                    ]);
                }
            }
        }

        if ($secrets !== (array) Setting::system(self::SECRET_SETTING, [])) {
            Setting::configureSystem(self::SECRET_SETTING, $secrets);
        }

        foreach (self::SECRET_FIELDS as $definition) {
            $ciphertext = $secrets[$definition['config']] ?? null;
            if (!is_string($ciphertext) || $ciphertext === '') {
                continue;
            }

            try {
                config([$definition['config'] => Crypt::decryptString($ciphertext)]);
            } catch (Throwable $exception) {
                config([$definition['config'] => null]);
                Log::error('Unable to decrypt a configured mail credential. Check APP_KEY before sending mail.', [
                    'setting' => $definition['setting'],
                    'exception' => get_class($exception),
                ]);
            }
        }
    }

    /**
     * Save non-secret provider settings while encrypting secrets separately.
     * Empty or omitted secrets preserve the existing credential.
     */
    public function save(array $input): void
    {
        $secrets = (array) Setting::system(self::SECRET_SETTING, []);
        $settings = [
            'mail.mailers.smtp' => (array) ($input['smtp'] ?? []),
            'mail.mailers.microsoft-graph' => (array) ($input['microsoftGraph'] ?? []),
            'services.mailgun' => (array) ($input['mailgun'] ?? []),
            'services.postmark' => (array) ($input['postmark'] ?? []),
            'services.sendgrid' => (array) ($input['sendgrid'] ?? []),
            'services.resend' => (array) ($input['resend'] ?? []),
        ];

        foreach (self::SECRET_FIELDS as $requestKey => $definition) {
            $providerKey = $this->requestProviderKey($requestKey);
            if (!array_key_exists($providerKey, $input) || !is_array($input[$providerKey])) {
                continue;
            }

            $provided = data_get($input[$providerKey], $definition['field']);
            if (is_string($provided) && trim($provided) !== '') {
                $secrets[$definition['config']] = Crypt::encryptString($provided);
            } elseif (empty($secrets[$definition['config']])) {
                $configuredSecret = config($definition['config']);
                if (is_string($configuredSecret) && $configuredSecret !== '') {
                    $secrets[$definition['config']] = Crypt::encryptString($configuredSecret);
                }
            }

            data_set($settings[$definition['setting']], $definition['field'], null);
        }

        Setting::configureSystem('mail.mailer', $input['mailer']);
        Setting::configureSystem('mail.from', (array) ($input['from'] ?? []));
        foreach ($settings as $key => $value) {
            Setting::configureSystem($key, $value);
        }
        Setting::configureSystem(self::SECRET_SETTING, $secrets);

        $this->hydrate();
    }

    /** Return the legacy settings form shape with blank secret fields and explicit configured flags. */
    public function redactedConfig(): array
    {
        $result = [
            'mailer' => config('mail.default'),
            'mailers' => ['smtp'],
            'fromAddress' => config('mail.from.address'),
            'fromName' => config('mail.from.name'),
        ];

        $configs = [
            'smtp' => config('mail.mailers.smtp', []),
            'microsoftGraph' => config('mail.mailers.microsoft-graph', []),
            'mailgun' => config('services.mailgun', []),
            'postmark' => config('services.postmark', []),
            'sendgrid' => config('services.sendgrid', []),
            'resend' => config('services.resend', []),
        ];

        foreach ($configs as $prefix => $values) {
            foreach ((array) $values as $key => $value) {
                if ($key === 'transport') {
                    continue;
                }

                $requestKey = $prefix . ucfirst($key);
                $secretName = $this->secretFieldForConfig($prefix, (string) $key);
                if ($secretName !== null) {
                    $definition = self::SECRET_FIELDS[$secretName];
                    $result[$secretName] = '';
                    $result[$secretName . 'Configured'] = $this->hasEncryptedSecret($definition['config']);
                } else {
                    $result[$requestKey] = $value;
                }
            }
        }

        foreach (self::SECRET_FIELDS as $requestKey => $definition) {
            $result[$requestKey] = '';
            $result[$requestKey . 'Configured'] = $this->hasEncryptedSecret($definition['config']);
        }

        return $result;
    }

    /** Build a temporary mail configuration for test-send without persisting submitted values. */
    public function testConfig(array $input): array
    {
        $config = [
            'mail.default' => $input['mailer'],
            'mail.from' => $input['from'],
        ];

        $secretMap = (array) Setting::system(self::SECRET_SETTING, []);
        foreach (self::SECRET_FIELDS as $requestKey => $definition) {
            $providerKey = $this->requestProviderKey($requestKey);
            $plain = data_get($input, $providerKey . '.' . $definition['field']);
            if (!is_string($plain) || trim($plain) === '') {
                $ciphertext = $secretMap[$definition['config']] ?? null;
                $plain = is_string($ciphertext) && $ciphertext !== ''
                    ? Crypt::decryptString($ciphertext)
                    : config($definition['config']);
            }

            $config[$definition['config']] = $plain;
        }

        foreach ([
            'smtp' => 'mail.mailers.smtp',
            'microsoftGraph' => 'mail.mailers.microsoft-graph',
            'mailgun' => 'services.mailgun',
            'postmark' => 'services.postmark',
            'sendgrid' => 'services.sendgrid',
            'resend' => 'services.resend',
        ] as $inputKey => $configKey) {
            $values = (array) ($input[$inputKey] ?? []);
            unset($values[$this->secretFieldForInput($inputKey)]);
            $config[$configKey] = array_merge((array) config($configKey, []), $values);
        }

        return $config;
    }

    private function hasEncryptedSecret(string $configPath): bool
    {
        return !empty(((array) Setting::system(self::SECRET_SETTING, []))[$configPath] ?? null)
            || (is_string(config($configPath)) && config($configPath) !== '');
    }

    private function requestProviderKey(string $requestKey): string
    {
        return match ($requestKey) {
            'smtpPassword' => 'smtp',
            'microsoftGraphClient_secret' => 'microsoftGraph',
            'mailgunSecret' => 'mailgun',
            'postmarkToken' => 'postmark',
            'sendgridApi_key' => 'sendgrid',
            default => 'resend',
        };
    }

    private function secretFieldForConfig(string $prefix, string $key): ?string
    {
        foreach (self::SECRET_FIELDS as $requestKey => $definition) {
            if ($definition['field'] === $key && $this->requestProviderKey($requestKey) === $prefix) {
                return $requestKey;
            }
        }

        return null;
    }

    private function secretFieldForInput(string $inputKey): string
    {
        foreach (self::SECRET_FIELDS as $requestKey => $definition) {
            if ($this->requestProviderKey($requestKey) === $inputKey) {
                return $definition['field'];
            }
        }

        return '';
    }
}
