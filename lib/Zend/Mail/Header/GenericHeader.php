<?php

namespace Zend\Mail\Header;

class GenericHeader
{
    private $name = '';
    private $value = '';

    public function __construct($name, $value = '')
    {
        $this->name = strtolower(trim((string)$name));
        $this->setFieldValue($value);
    }

    public function setFieldValue($value)
    {
        $this->value = trim((string)$value);
        return $this;
    }

    public function getFieldValue()
    {
        return $this->value;
    }

    public function getFieldName()
    {
        return $this->name;
    }
}
