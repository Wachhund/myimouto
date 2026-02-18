<?php

namespace MyImouto\Mail\Transport;

class Smtp
{
    private $options;

    public function setOptions(SmtpOptions $options)
    {
        $this->options = $options;
        return $this;
    }

    public function send($message)
    {
        if (!class_exists('\\MyImouto\\Mail\\PHPMailerTransport')) {
            throw new \RuntimeException('SMTP transport unavailable: PHPMailer transport not loaded');
        }

        $options = $this->options ? $this->options->toArray() : [];
        $connection = isset($options['connection_config']) && is_array($options['connection_config']) ?
            $options['connection_config'] :
            [];

        $smtpSettings = [
            'address' => isset($options['host']) ? (string)$options['host'] : '127.0.0.1',
            'domain' => isset($options['name']) ? (string)$options['name'] : 'localhost',
            'port' => isset($options['port']) ? (int)$options['port'] : 25,
            'authentication' => isset($options['connection_class']) ? (string)$options['connection_class'] : 'login',
            'user_name' => isset($connection['username']) ? (string)$connection['username'] : '',
            'password' => isset($connection['password']) ? (string)$connection['password'] : '',
            'enable_starttls_auto' => !empty($connection['ssl']) && strtolower((string)$connection['ssl']) === 'tls',
        ];

        $transport = new \MyImouto\Mail\PHPMailerTransport($smtpSettings);
        $transport->send($message);

        return $this;
    }
}
