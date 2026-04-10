<?php

namespace MyImouto\Mail\Transport;

class File
{
    private $options;

    public function setOptions(FileOptions $options)
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions()
    {
        if (!$this->options) {
            $this->options = new FileOptions();
        }

        return $this->options;
    }

    public function send($message)
    {
        if (!is_object($message) || !method_exists($message, 'isValid') || !$message->isValid()) {
            throw new \RuntimeException('Cannot send mail: invalid message');
        }

        $path = $this->resolvePath();
        $file = $path . DIRECTORY_SEPARATOR . $this->resolveFilename();

        $payload = $this->renderMessage($message);
        if (file_put_contents($file, $payload) === false) {
            throw new \RuntimeException(sprintf('Unable to write mail file: %s', $file));
        }

        return $this;
    }

    private function resolvePath()
    {
        $path = trim((string) $this->getOptions()->getPath());
        if ($path === '') {
            if (class_exists('\\Rails', false)) {
                $path = rtrim((string) \Rails::root(), '/\\') . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'mail';
            } else {
                $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mail';
            }
        }

        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to create mail directory: %s', $path));
        }

        return $path;
    }

    private function resolveFilename()
    {
        $callback = $this->getOptions()->getCallback();
        if ($callback) {
            $filename = (string) call_user_func($callback);
            $filename = trim($filename);
            if ($filename !== '') {
                return $filename;
            }
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            $suffix = dechex(mt_rand(0, 0x7fffffff));
        }

        return sprintf(
            'action_mailer_%s_%s.tmp',
            gmdate('YmdHis'),
            $suffix,
        );
    }

    private function renderMessage($message)
    {
        $lines = [];
        $lines[] = 'Date: ' . gmdate(DATE_RFC2822);
        $lines[] = 'Subject: ' . (string) $this->safeCall($message, 'getSubject', '');
        $lines[] = 'From: ' . $this->formatAddresses($this->safeCall($message, 'getFrom', []));
        $lines[] = 'To: ' . $this->formatAddresses($this->safeCall($message, 'getTo', []));

        $cc = $this->formatAddresses($this->safeCall($message, 'getCc', []));
        if ($cc !== '') {
            $lines[] = 'Cc: ' . $cc;
        }

        $bcc = $this->formatAddresses($this->safeCall($message, 'getBcc', []));
        if ($bcc !== '') {
            $lines[] = 'Bcc: ' . $bcc;
        }

        $replyTo = $this->formatAddresses($this->safeCall($message, 'getReplyTo', []));
        if ($replyTo !== '') {
            $lines[] = 'Reply-To: ' . $replyTo;
        }

        $lines[] = '';
        $lines[] = $this->renderBody($this->safeCall($message, 'getBody', ''));

        return implode("\n", $lines) . "\n";
    }

    private function renderBody($body)
    {
        if (is_object($body) && method_exists($body, 'getParts')) {
            $parts = [];
            foreach ($body->getParts() as $part) {
                if (is_object($part) && method_exists($part, 'getRawContent')) {
                    $parts[] = $this->contentToString($part->getRawContent());
                    continue;
                }

                $parts[] = $this->contentToString($part);
            }

            return implode("\n\n", $parts);
        }

        return $this->contentToString($body);
    }

    private function formatAddresses($addresses)
    {
        $list = [];
        if (!is_array($addresses) && !$addresses instanceof \Traversable) {
            return '';
        }

        foreach ($addresses as $address) {
            $email = '';
            $name = '';

            if (is_object($address)) {
                if (method_exists($address, 'getEmail')) {
                    $email = trim((string) $address->getEmail());
                } elseif (isset($address->email)) {
                    $email = trim((string) $address->email);
                }

                if (method_exists($address, 'getName')) {
                    $name = trim((string) $address->getName());
                } elseif (isset($address->name)) {
                    $name = trim((string) $address->name);
                }
            } elseif (is_string($address)) {
                $email = trim($address);
            }

            if ($email === '') {
                continue;
            }

            if ($name !== '') {
                $list[] = sprintf('"%s" <%s>', str_replace('"', '\"', $name), $email);
            } else {
                $list[] = $email;
            }
        }

        return implode(', ', $list);
    }

    private function contentToString($value)
    {
        if (is_resource($value)) {
            $data = stream_get_contents($value);
            $meta = stream_get_meta_data($value);
            if (!empty($meta['seekable'])) {
                rewind($value);
            }
            return (string) $data;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return (string) $value;
    }

    private function safeCall($object, $method, $default = null)
    {
        if (!is_object($object) || !method_exists($object, $method)) {
            return $default;
        }

        return $object->{$method}();
    }
}
