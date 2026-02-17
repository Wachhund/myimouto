<?php

namespace Zend\Mail\Transport;

class SmtpOptions
{
    private $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray()
    {
        return $this->options;
    }
}
