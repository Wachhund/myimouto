<?php

namespace MyImouto\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

class PHPMailerTransport
{
    private const MIME_OCTETSTREAM = 'application/octet-stream';
    private const DISPOSITION_ATTACHMENT = 'attachment';
    private const DISPOSITION_INLINE = 'inline';
    private const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    private const ENCODING_7BIT = '7bit';
    private const ENCODING_8BIT = '8bit';
    private const ENCODING_BASE64 = 'base64';

    protected $settings = [];

    public function __construct(array $settings = [])
    {
        if (!empty($settings['address']) && empty($settings['host'])) {
            $settings['host'] = $settings['address'];
        }

        $defaults = [
            'host' => '127.0.0.1',
            'port' => 587,
            'domain' => 'localhost',
            'authentication' => 'none',
            'user_name' => '',
            'password' => '',
            'enable_starttls_auto' => true,
            'timeout' => 15,
            'transport_label' => 'phpmailer',
        ];

        $this->settings = array_merge($defaults, $settings);
    }

    public function send($message)
    {
        if (!$this->isValidMessage($message)) {
            throw new RuntimeException('Cannot send mail: invalid message.');
        }

        $mailer = new PHPMailer(true);
        // Keep legacy compatibility: local domains like admin@myimouto are used in old configs.
        PHPMailer::$validator = static function ($address) {
            $address = (string)$address;
            return strpos($address, '@') !== false &&
                   strpos($address, "\n") === false &&
                   strpos($address, "\r") === false;
        };

        $this->logInfo('mail.transport.selected', [
            'transport' => (string)$this->settings['transport_label'],
            'host' => (string)$this->settings['host'],
            'port' => (int)$this->settings['port'],
            'auth' => strtolower(trim((string)$this->settings['authentication'])),
            'starttls' => !empty($this->settings['enable_starttls_auto']),
            'timeout' => (int)$this->settings['timeout'],
        ]);

        try {
            $this->configureSmtp($mailer);
            $this->applyEnvelope($mailer, $message);
            $this->applyBody($mailer, $message);
            $mailer->send();

            $this->logInfo('mail.delivery.success', [
                'transport' => (string)$this->settings['transport_label'],
                'host' => (string)$this->settings['host'],
            ]);
        } catch (PHPMailerException $e) {
            $this->logWarning('mail.delivery.failure', [
                'transport' => (string)$this->settings['transport_label'],
                'host' => (string)$this->settings['host'],
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('PHPMailer transport failed: ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            $this->logWarning('mail.delivery.failure', [
                'transport' => (string)$this->settings['transport_label'],
                'host' => (string)$this->settings['host'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function configureSmtp(PHPMailer $mailer)
    {
        $mailer->isSMTP();
        $mailer->Host = (string)$this->settings['host'];
        $mailer->Port = (int)$this->settings['port'];
        $mailer->Helo = (string)$this->settings['domain'];
        $mailer->Timeout = (int)$this->settings['timeout'];

        $auth = strtolower(trim((string)$this->settings['authentication']));
        $authRequired = !in_array($auth, ['', 'none'], true);

        if ($authRequired) {
            $mailer->SMTPAuth = true;
            $mailer->AuthType = $this->toPhpmailerAuthType($auth);
            $mailer->Username = (string)$this->settings['user_name'];
            $mailer->Password = (string)$this->settings['password'];
        } else {
            $mailer->SMTPAuth = false;
        }

        if (!empty($this->settings['enable_starttls_auto'])) {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    protected function applyEnvelope(PHPMailer $mailer, $message)
    {
        $charset = (string)$this->safeCall($message, 'getEncoding', '');
        if ($charset !== '') {
            $mailer->CharSet = $charset;
        }

        $this->setFrom($mailer, $this->safeCall($message, 'getFrom'));
        $this->appendAddresses($mailer, 'addAddress', $this->safeCall($message, 'getTo'));
        $this->appendAddresses($mailer, 'addCC', $this->safeCall($message, 'getCc'));
        $this->appendAddresses($mailer, 'addBCC', $this->safeCall($message, 'getBcc'));
        $this->appendAddresses($mailer, 'addReplyTo', $this->safeCall($message, 'getReplyTo'));

        $mailer->Subject = (string)$this->safeCall($message, 'getSubject', '');
    }

    protected function setFrom(PHPMailer $mailer, $addresses)
    {
        foreach ($this->iterateAddresses($addresses) as $address) {
            $mailer->setFrom($address['email'], $address['name'], false);
            return;
        }

        throw new RuntimeException('Cannot send mail: missing From address.');
    }

    protected function appendAddresses(PHPMailer $mailer, $method, $addresses)
    {
        foreach ($this->iterateAddresses($addresses) as $address) {
            $mailer->{$method}($address['email'], $address['name']);
        }
    }

    protected function applyBody(PHPMailer $mailer, $message)
    {
        $body = $this->safeCall($message, 'getBody', '');

        if ($this->isMultipartBody($body)) {
            $this->applyMimeBody($mailer, $body);
            return;
        }

        $mailer->isHTML(false);
        $mailer->Body = (string)$body;
    }

    protected function applyMimeBody(PHPMailer $mailer, $body)
    {
        $plainBody = null;
        $htmlBody = null;
        $attachmentIndex = 0;

        foreach ($this->safeCall($body, 'getParts', []) as $part) {
            $mimeType = strtolower($this->partProp($part, 'type'));
            $disposition = strtolower($this->partProp($part, 'disposition'));
            $isAttachment = in_array($disposition, [self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE], true);

            if (!$isAttachment && str_starts_with($mimeType, 'text/plain')) {
                $plainBody = $this->partContent($part);
                continue;
            }

            if (!$isAttachment && str_starts_with($mimeType, 'text/html')) {
                $htmlBody = $this->partContent($part);
                continue;
            }

            $attachmentIndex++;
            $this->addAttachment($mailer, $part, $attachmentIndex, $mimeType, $disposition);
        }

        if ($htmlBody !== null) {
            $mailer->isHTML(true);
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $plainBody !== null ? $plainBody : strip_tags($htmlBody);
            return;
        }

        $mailer->isHTML(false);
        $mailer->Body = $plainBody !== null ? $plainBody : '';
    }

    protected function addAttachment(PHPMailer $mailer, $part, $index, $mimeType, $disposition)
    {
        $content = $this->partContent($part);
        $name = $this->partProp($part, 'filename');
        if ($name === '') {
            $name = 'attachment-' . $index;
        }

        $encoding = $this->toPhpmailerEncoding($this->partProp($part, 'encoding'));
        if ($mimeType === '') {
            $mimeType = self::MIME_OCTETSTREAM;
        }

        if ($disposition === self::DISPOSITION_INLINE) {
            $cid = trim($this->partProp($part, 'id'), '<>');
            if ($cid === '') {
                $cid = 'inline-' . $index . '@myimouto.local';
            }
            $mailer->addStringEmbeddedImage($content, $cid, $name, $encoding, $mimeType);
            return;
        }

        $mailer->addStringAttachment($content, $name, $encoding, $mimeType, self::DISPOSITION_ATTACHMENT);
    }

    protected function partContent($part)
    {
        $content = $this->safeCall($part, 'getRawContent', null);
        if ($content === null) {
            $content = $this->safeCall($part, 'getContent', '');
        }

        if (is_resource($content)) {
            $data = stream_get_contents($content);
            $meta = stream_get_meta_data($content);
            if (!empty($meta['seekable'])) {
                rewind($content);
            }
            return (string)$data;
        }

        if ($content === '' && is_object($part) && method_exists($part, '__toString')) {
            return (string)$part;
        }

        return (string)$content;
    }

    protected function toPhpmailerEncoding($encoding)
    {
        $encoding = strtolower(trim($encoding));

        switch ($encoding) {
            case self::ENCODING_QUOTED_PRINTABLE:
                return PHPMailer::ENCODING_QUOTED_PRINTABLE;
            case self::ENCODING_7BIT:
                return PHPMailer::ENCODING_7BIT;
            case self::ENCODING_8BIT:
                return PHPMailer::ENCODING_8BIT;
            case 'binary':
                return PHPMailer::ENCODING_BINARY;
            case self::ENCODING_BASE64:
            default:
                return PHPMailer::ENCODING_BASE64;
        }
    }

    protected function toPhpmailerAuthType($auth)
    {
        $auth = strtolower(trim((string)$auth));

        switch ($auth) {
            case 'plain':
                return 'PLAIN';
            case 'cram-md5':
            case 'crammd5':
                return 'CRAM-MD5';
            case 'xoauth2':
                return 'XOAUTH2';
            case 'login':
            default:
                return 'LOGIN';
        }
    }

    protected function isValidMessage($message)
    {
        if (!is_object($message) || !method_exists($message, 'isValid')) {
            return false;
        }

        try {
            return (bool)$message->isValid();
        } catch (Throwable $e) {
            $this->logWarning('mail.delivery.failure', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function isMultipartBody($body)
    {
        return is_object($body) && method_exists($body, 'getParts');
    }

    protected function iterateAddresses($addresses)
    {
        if (is_string($addresses)) {
            $email = trim($addresses);
            if ($email !== '') {
                yield ['email' => $email, 'name' => ''];
            }
            return;
        }

        if (!is_array($addresses) && !$addresses instanceof \Traversable) {
            return;
        }

        foreach ($addresses as $address) {
            $normalized = $this->normalizeAddress($address);
            if ($normalized !== null) {
                yield $normalized;
            }
        }
    }

    protected function normalizeAddress($address)
    {
        if (is_string($address)) {
            $email = trim($address);
            if ($email === '') {
                return null;
            }

            return ['email' => $email, 'name' => ''];
        }

        if (is_array($address)) {
            if (isset($address['email'])) {
                $email = trim((string)$address['email']);
                if ($email === '') {
                    return null;
                }

                return [
                    'email' => $email,
                    'name' => isset($address['name']) ? (string)$address['name'] : '',
                ];
            }

            if (count($address) === 1) {
                $email = trim((string)key($address));
                if ($email === '') {
                    return null;
                }

                return [
                    'email' => $email,
                    'name' => (string)current($address),
                ];
            }
        }

        if (is_object($address)) {
            $email = '';
            $name = '';

            if (method_exists($address, 'getEmail')) {
                $email = trim((string)$address->getEmail());
            } elseif (isset($address->email)) {
                $email = trim((string)$address->email);
            }

            if ($email === '') {
                return null;
            }

            if (method_exists($address, 'getName')) {
                $name = (string)$address->getName();
            } elseif (isset($address->name)) {
                $name = (string)$address->name;
            }

            return ['email' => $email, 'name' => $name];
        }

        return null;
    }

    protected function partProp($part, $name)
    {
        if (!is_object($part)) {
            return '';
        }

        if (isset($part->{$name})) {
            return trim((string)$part->{$name});
        }

        $method = 'get' . ucfirst($name);
        if (method_exists($part, $method)) {
            return trim((string)$part->{$method}());
        }

        return '';
    }

    protected function safeCall($object, $method, $default = null)
    {
        if (!is_object($object) || !method_exists($object, $method)) {
            return $default;
        }

        return $object->{$method}();
    }

    protected function logInfo($event, array $context = [])
    {
        $this->log('info', $event, $context);
    }

    protected function logWarning($event, array $context = [])
    {
        $this->log('warning', $event, $context);
    }

    protected function log($level, $event, array $context = [])
    {
        if (!class_exists('\\Rails', false)) {
            return;
        }

        $logger = \Rails::log();
        if (!$logger) {
            return;
        }

        $context = $this->sanitizeContext($context);
        $message = json_encode([
            'event' => (string)$event,
            'context' => $context,
        ]);
        if ($message === false) {
            $message = (string)$event;
        }

        if (method_exists($logger, $level)) {
            $logger->{$level}($message);
            return;
        }

        $logger->warning($message);
    }

    protected function sanitizeContext(array $context)
    {
        unset($context['password'], $context['smtp_password']);

        return $context;
    }
}
