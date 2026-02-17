<?php

namespace Zend\Mail\Transport;

class FileOptions
{
    private $path = '';
    private $callback;

    public function __construct(array $options = [])
    {
        if (isset($options['path'])) {
            $this->setPath($options['path']);
        }
        if (isset($options['callback'])) {
            $this->setCallback($options['callback']);
        }
    }

    public function setPath($path)
    {
        $this->path = trim((string)$path);
        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('File callback must be callable');
        }

        $this->callback = $callback;
        return $this;
    }

    public function getCallback()
    {
        return $this->callback;
    }
}
