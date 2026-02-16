<?php
MyImouto\Application::configure(function($config) {
    $config->error->report_types = E_WARNING;
    
    $config->serve_static_assets = true;
    
    $config->consider_all_requests_local = false;
    
    $config->active_record->use_cached_schema = true;
    
    $config->assets->digest = true;

    $mailRuntimeConfig = \MyImouto\Mail\MailRuntimeConfig::fromEnvironment();

    $mail_file_path = $mailRuntimeConfig['mail_file_path'];
    if (!is_dir($mail_file_path) && !file_exists($mail_file_path)) {
        try {
            mkdir($mail_file_path, 0777, true);
        } catch (\Throwable $e) {
            Rails::log()->exception($e);
        }
    }
    $config->action_mailer->file_settings->location = $mail_file_path;

    // Never use implicit sendmail in production: prefer explicit SMTP, fallback to file transport.
    if ($mailRuntimeConfig['smtp_configured']) {
        if (!empty($mailRuntimeConfig['transport_warning'])) {
            Rails::log()->warning($mailRuntimeConfig['transport_warning']);
        }

        $smtpSettings = $mailRuntimeConfig['smtp_settings'];
        $config->action_mailer->delivery_method = function() use ($smtpSettings) {
            return new \MyImouto\Mail\PHPMailerTransport($smtpSettings);
        };
    } else {
        $config->action_mailer->delivery_method = 'file';
    }
});
