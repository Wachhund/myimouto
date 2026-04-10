<?php

namespace MyImouto\Mail;

class Address
{
    private $email = '';
    private $name = '';

    public function __construct($email, $name = '')
    {
        $email = trim((string) $email);
        $name = trim((string) $name);

        if ($email === '') {
            throw new \InvalidArgumentException('Email cannot be empty');
        }
        if (strpos($email, "\r") !== false || strpos($email, "\n") !== false) {
            throw new \InvalidArgumentException('Email contains invalid characters');
        }

        $this->email = $email;
        $this->name = $name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getName()
    {
        return $this->name;
    }
}
