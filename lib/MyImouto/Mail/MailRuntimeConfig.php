<?php

namespace MyImouto\Mail;

final class MailRuntimeConfig
{
    private const DEFAULT_SMTP_SETTINGS = [
        'host' => '127.0.0.1',
        'address' => '127.0.0.1',
        'port' => 587,
        'domain' => 'localhost',
        'authentication' => 'none',
        'user_name' => '',
        'password' => '',
        'enable_starttls_auto' => true,
        'timeout' => 15,
        'transport_label' => 'phpmailer',
    ];

    public static function fromEnvironment()
    {
        $smtpAddress = self::env('MYIMOUTO_SMTP_ADDRESS');
        $smtpUser = self::env('MYIMOUTO_SMTP_USERNAME');
        $smtpPassword = self::env('MYIMOUTO_SMTP_PASSWORD');
        $smtpAuth = strtolower(self::env('MYIMOUTO_SMTP_AUTH', 'login'));

        $authRequired = !in_array($smtpAuth, ['', 'none'], true);
        $hasCredentials = ($smtpUser !== '' && $smtpPassword !== '');

        $requestedTransport = strtolower(self::env('MYIMOUTO_SMTP_TRANSPORT', 'phpmailer'));
        $transportWarning = self::transportWarning($requestedTransport);

        $smtpSettings = self::normalizeSmtpSettings([
            'host' => $smtpAddress,
            'address' => $smtpAddress,
            'port' => self::env('MYIMOUTO_SMTP_PORT', '587'),
            'domain' => self::env('MYIMOUTO_SMTP_DOMAIN', 'localhost'),
            'authentication' => $authRequired ? $smtpAuth : 'none',
            'user_name' => $smtpUser,
            'password' => $smtpPassword,
            'enable_starttls_auto' => self::toBool(self::env('MYIMOUTO_SMTP_STARTTLS', '1'), true),
            'timeout' => self::env('MYIMOUTO_SMTP_TIMEOUT', '15'),
            'transport_label' => 'phpmailer',
        ]);

        return [
            'mail_file_path' => self::env('MYIMOUTO_MAIL_FILE_PATH', self::defaultMailFilePath()),
            'smtp_configured' => ($smtpAddress !== '') && (!$authRequired || $hasCredentials),
            'smtp_settings' => $smtpSettings,
            'transport_warning' => $transportWarning,
        ];
    }

    public static function normalizeSmtpSettings(array $settings = [])
    {
        $normalized = array_merge(self::DEFAULT_SMTP_SETTINGS, $settings);

        $normalized['host'] = trim((string) $normalized['host']);
        $normalized['address'] = trim((string) $normalized['address']);
        if ($normalized['host'] === '' && $normalized['address'] !== '') {
            $normalized['host'] = $normalized['address'];
        }
        if ($normalized['address'] === '' && $normalized['host'] !== '') {
            $normalized['address'] = $normalized['host'];
        }

        $normalized['port'] = self::toPositiveInt($normalized['port'], (int) self::DEFAULT_SMTP_SETTINGS['port']);
        $normalized['timeout'] = self::toPositiveInt($normalized['timeout'], (int) self::DEFAULT_SMTP_SETTINGS['timeout']);

        $normalized['domain'] = trim((string) $normalized['domain']);
        if ($normalized['domain'] === '') {
            $normalized['domain'] = (string) self::DEFAULT_SMTP_SETTINGS['domain'];
        }

        $normalized['authentication'] = strtolower(trim((string) $normalized['authentication']));
        if ($normalized['authentication'] === '') {
            $normalized['authentication'] = (string) self::DEFAULT_SMTP_SETTINGS['authentication'];
        }

        $normalized['user_name'] = trim((string) $normalized['user_name']);
        $normalized['password'] = (string) $normalized['password'];

        $normalized['enable_starttls_auto'] = self::toBool(
            $normalized['enable_starttls_auto'],
            (bool) self::DEFAULT_SMTP_SETTINGS['enable_starttls_auto'],
        );

        $normalized['transport_label'] = trim((string) $normalized['transport_label']);
        if ($normalized['transport_label'] === '') {
            $normalized['transport_label'] = (string) self::DEFAULT_SMTP_SETTINGS['transport_label'];
        }

        return $normalized;
    }

    private static function defaultMailFilePath()
    {
        $root = dirname(__DIR__, 3);
        if (class_exists('\\Rails', false)) {
            $root = \Rails::root();
        }

        return rtrim((string) $root, '/\\') . '/tmp/mail';
    }

    private static function env($name, $default = '')
    {
        $value = getenv($name);
        if ($value === false) {
            return (string) $default;
        }

        return trim((string) $value);
    }

    private static function toBool($value, $default = false)
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return !in_array($normalized, ['0', 'false', 'off', 'no'], true);
    }

    private static function toPositiveInt($value, $default)
    {
        $int = (int) $value;
        if ($int <= 0) {
            return $default;
        }

        return $int;
    }

    private static function transportWarning($requestedTransport)
    {
        if ($requestedTransport === '' || $requestedTransport === 'phpmailer') {
            return null;
        }

        if ($requestedTransport === 'zend') {
            return '[mail.transport] MYIMOUTO_SMTP_TRANSPORT=zend is deprecated and ignored; using phpmailer';
        }

        return sprintf('[mail.transport] Unknown MYIMOUTO_SMTP_TRANSPORT=%s; using phpmailer', $requestedTransport);
    }
}
