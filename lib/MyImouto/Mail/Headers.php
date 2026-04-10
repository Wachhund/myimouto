<?php

namespace MyImouto\Mail;

use MyImouto\Mail\Header\ContentType;
use MyImouto\Mail\Header\GenericHeader;

class Headers
{
    private $headers = [];

    public function get($name)
    {
        $key = strtolower(trim((string) $name));
        if ($key === '') {
            throw new \InvalidArgumentException('Header name cannot be empty');
        }

        if (!isset($this->headers[$key])) {
            $this->headers[$key] = $this->createHeader($key);
        }

        return $this->headers[$key];
    }

    public function set($name, $header)
    {
        $key = strtolower(trim((string) $name));
        if ($key === '') {
            throw new \InvalidArgumentException('Header name cannot be empty');
        }

        $this->headers[$key] = $header;
        return $this;
    }

    public function toArray()
    {
        return $this->headers;
    }

    private function createHeader($name)
    {
        if ($name === 'content-type') {
            return new ContentType();
        }

        return new GenericHeader($name);
    }
}
