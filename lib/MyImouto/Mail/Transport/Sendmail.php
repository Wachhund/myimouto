<?php

namespace MyImouto\Mail\Transport;

class Sendmail
{
    public function send($message)
    {
        throw new \RuntimeException(
            'Sendmail transport is disabled. Use explicit SMTP (PHPMailer) or file delivery.',
        );
    }
}
