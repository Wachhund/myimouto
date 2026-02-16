<?php
MyImouto\Application::configure(function($config) {
    $config->error->report_types = E_WARNING;
    
    $config->serve_static_assets = true;
    
    $config->consider_all_requests_local = false;
    
    $config->active_record->use_cached_schema = true;
    
    $config->assets->digest = true;

    $env = function($name, $default = '') {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }
        return trim((string)$value);
    };

    $to_bool = function($value, $default = false) {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }
        return !in_array($normalized, ['0', 'false', 'off', 'no'], true);
    };

    $mail_file_path = $env('MYIMOUTO_MAIL_FILE_PATH', Rails::root() . '/tmp/mail');
    if (!is_dir($mail_file_path) && !file_exists($mail_file_path)) {
        try {
            mkdir($mail_file_path, 0777, true);
        } catch (\Throwable $e) {
            Rails::log()->exception($e);
        }
    }
    $config->action_mailer->file_settings->location = $mail_file_path;

    // Never use implicit sendmail in production: prefer explicit SMTP, fallback to file transport.
    $smtp_address = $env('MYIMOUTO_SMTP_ADDRESS');
    $smtp_username = $env('MYIMOUTO_SMTP_USERNAME');
    $smtp_password = $env('MYIMOUTO_SMTP_PASSWORD');
    $smtp_auth = strtolower($env('MYIMOUTO_SMTP_AUTH', 'login'));
    $smtp_transport = strtolower($env('MYIMOUTO_SMTP_TRANSPORT', 'phpmailer'));

    $auth_required = !in_array($smtp_auth, ['', 'none'], true);
    $has_credentials = ($smtp_username !== '' && $smtp_password !== '');
    $smtp_configured = ($smtp_address !== '') && (!$auth_required || $has_credentials);

    if ($smtp_configured) {
        $smtp_port = (int)$env('MYIMOUTO_SMTP_PORT', '587');
        if ($smtp_port <= 0) {
            $smtp_port = 587;
        }
        $smtp_settings = [
            'address' => $smtp_address,
            'port' => $smtp_port,
            'domain' => $env('MYIMOUTO_SMTP_DOMAIN', 'localhost'),
            'authentication' => $auth_required ? $smtp_auth : 'none',
            'user_name' => $smtp_username,
            'password' => $smtp_password,
            'enable_starttls_auto' => $to_bool($env('MYIMOUTO_SMTP_STARTTLS', '1'), true)
        ];

        if ($smtp_transport === 'zend') {
            $config->action_mailer->delivery_method = 'smtp';
            $config->action_mailer->smtp_settings->address = $smtp_settings['address'];
            $config->action_mailer->smtp_settings->port = $smtp_settings['port'];
            $config->action_mailer->smtp_settings->domain = $smtp_settings['domain'];
            $config->action_mailer->smtp_settings->authentication = $auth_required ? $smtp_settings['authentication'] : null;
            $config->action_mailer->smtp_settings->user_name = $smtp_settings['user_name'];
            $config->action_mailer->smtp_settings->password = $smtp_settings['password'];
            $config->action_mailer->smtp_settings->enable_starttls_auto = $smtp_settings['enable_starttls_auto'];
        } else {
            // Use PHPMailer for SMTP by default, keep "zend" as an explicit opt-out.
            $config->action_mailer->delivery_method = function() use ($smtp_settings) {
                return new \MyImouto\Mail\PHPMailerTransport($smtp_settings);
            };
        }
    } else {
        $config->action_mailer->delivery_method = 'file';
    }
});
