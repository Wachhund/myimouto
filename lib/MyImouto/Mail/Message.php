<?php

namespace MyImouto\Mail;

class Message
{
    private $encoding;
    private $subject = '';
    private $body = '';
    private $headers;

    private $from = [];
    private $to = [];
    private $cc = [];
    private $bcc = [];
    private $replyTo = [];

    public function __construct()
    {
        $this->headers = new Headers();
    }

    public function setEncoding($encoding)
    {
        $encoding = trim((string)$encoding);
        $this->encoding = $encoding === '' ? null : $encoding;
        return $this;
    }

    public function getEncoding()
    {
        return $this->encoding;
    }

    public function setFrom($email, $name = null)
    {
        $this->from = [];
        $this->appendAddresses($this->from, $email, $name);
        return $this;
    }

    public function addTo($email, $name = null)
    {
        $this->appendAddresses($this->to, $email, $name);
        return $this;
    }

    public function addCc($email, $name = null)
    {
        $this->appendAddresses($this->cc, $email, $name);
        return $this;
    }

    public function addBcc($email, $name = null)
    {
        $this->appendAddresses($this->bcc, $email, $name);
        return $this;
    }

    public function addReplyTo($email, $name = null)
    {
        $this->appendAddresses($this->replyTo, $email, $name);
        return $this;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function getCc()
    {
        return $this->cc;
    }

    public function getBcc()
    {
        return $this->bcc;
    }

    public function getReplyTo()
    {
        return $this->replyTo;
    }

    public function setSubject($subject)
    {
        $this->subject = (string)$subject;
        return $this;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setBody($body)
    {
        $this->body = $body;
        if (is_object($body) && method_exists($body, 'getParts')) {
            $this->headers->get('content-type')->setType('multipart/mixed');
        } else {
            $this->headers->get('content-type')->setType('text/plain');
        }

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function isValid()
    {
        return !empty($this->from) && !empty($this->to);
    }

    private function appendAddresses(array &$target, $input, $name = null)
    {
        foreach ($this->normalizeAddresses($input, $name) as $address) {
            $target[] = $address;
        }
    }

    private function normalizeAddresses($input, $name = null)
    {
        if ($input instanceof Address) {
            return [$input];
        }

        if (is_string($input)) {
            $input = trim($input);
            if ($input === '') {
                return [];
            }
            return [new Address($input, $name === null ? '' : $name)];
        }

        if (is_object($input)) {
            $address = $this->fromObject($input);
            return $address ? [$address] : [];
        }

        if (!is_array($input) && !$input instanceof \Traversable) {
            return [];
        }

        if (is_array($input) && $this->isAddressTuple($input)) {
            $address = $this->fromArray($input);
            return $address ? [$address] : [];
        }

        $normalized = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $address = $this->fromKeyValue($key, $value);
                if ($address) {
                    $normalized[] = $address;
                }
                continue;
            }

            if ($value instanceof Address) {
                $normalized[] = $value;
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    $normalized[] = new Address($value, '');
                }
                continue;
            }

            if (is_array($value)) {
                $address = $this->fromArray($value);
                if ($address) {
                    $normalized[] = $address;
                }
                continue;
            }

            if (is_object($value)) {
                $address = $this->fromObject($value);
                if ($address) {
                    $normalized[] = $address;
                }
            }
        }

        return $normalized;
    }

    private function isAddressTuple(array $value)
    {
        if (!isset($value[0])) {
            return false;
        }

        if (!is_string($value[0])) {
            return false;
        }

        if (strpos($value[0], '@') === false) {
            return false;
        }

        return count($value) <= 2;
    }

    private function fromKeyValue($email, $name)
    {
        $email = trim((string)$email);
        if ($email === '') {
            return null;
        }

        return new Address($email, (string)$name);
    }

    private function fromArray(array $value)
    {
        if (isset($value['email'])) {
            $email = trim((string)$value['email']);
            if ($email === '') {
                return null;
            }
            $name = isset($value['name']) ? (string)$value['name'] : '';
            return new Address($email, $name);
        }

        if (isset($value[0])) {
            $email = trim((string)$value[0]);
            if ($email === '') {
                return null;
            }
            $name = isset($value[1]) ? (string)$value[1] : '';
            return new Address($email, $name);
        }

        return null;
    }

    private function fromObject($value)
    {
        $email = '';
        $name = '';

        if (method_exists($value, 'getEmail')) {
            $email = trim((string)$value->getEmail());
        } elseif (isset($value->email)) {
            $email = trim((string)$value->email);
        }

        if ($email === '') {
            return null;
        }

        if (method_exists($value, 'getName')) {
            $name = (string)$value->getName();
        } elseif (isset($value->name)) {
            $name = (string)$value->name;
        }

        return new Address($email, $name);
    }
}
