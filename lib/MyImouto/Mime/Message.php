<?php

namespace MyImouto\Mime;

class Message
{
    private $parts = [];

    public function addPart(Part $part)
    {
        $this->parts[] = $part;
        return $this;
    }

    public function setParts(array $parts)
    {
        $this->parts = [];
        foreach ($parts as $part) {
            if ($part instanceof Part) {
                $this->parts[] = $part;
            }
        }

        return $this;
    }

    public function getParts()
    {
        return $this->parts;
    }

    public function __toString()
    {
        $out = [];
        foreach ($this->parts as $part) {
            $out[] = (string)$part;
        }

        return implode("\n\n", $out);
    }
}
