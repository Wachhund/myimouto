<?php

namespace Zend\Mail\Header;

class ContentType
{
    private $type = 'text/plain';

    public function __construct($type = 'text/plain')
    {
        $this->setType($type);
    }

    public function setType($type)
    {
        $type = trim((string)$type);
        if ($type === '') {
            $type = 'text/plain';
        }

        $this->type = $type;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }
}
