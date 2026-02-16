<?php

namespace MyImouto\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Zend\Mail\Address\AddressInterface;
use Zend\Mail\AddressList;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Mime;
use Zend\Mime\Part as MimePart;

class PHPMailerTransport implements TransportInterface
{
    protected $settings = [];

    public function __construct(array $settings = [])
    {
        $defaults = [
            'host' => '127.0.0.1',
            'port' => 587,
            'domain' => 'localhost',
            'authentication' => 'login',
            'user_name' => '',
            'password' => '',
            'enable_starttls_auto' => true,
            'timeout' => 30
        ];

        $this->settings = array_merge($defaults, $settings);
    }

    public function send(Message $message)
    {
        if (!$message->isValid()) {
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

        try {
            $this->configureSmtp($mailer);
            $this->applyEnvelope($mailer, $message);
            $this->applyBody($mailer, $message);
            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('PHPMailer transport failed: ' . $e->getMessage(), 0, $e);
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
            $mailer->AuthType = $auth;
            $mailer->Username = (string)$this->settings['user_name'];
            $mailer->Password = (string)$this->settings['password'];
        } else {
            $mailer->SMTPAuth = false;
        }

        if (!empty($this->settings['enable_starttls_auto'])) {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    protected function applyEnvelope(PHPMailer $mailer, Message $message)
    {
        $charset = (string)$message->getEncoding();
        if ($charset !== '') {
            $mailer->CharSet = $charset;
        }

        $this->setFrom($mailer, $message->getFrom());
        $this->appendAddresses($mailer, 'addAddress', $message->getTo());
        $this->appendAddresses($mailer, 'addCC', $message->getCc());
        $this->appendAddresses($mailer, 'addBCC', $message->getBcc());
        $this->appendAddresses($mailer, 'addReplyTo', $message->getReplyTo());

        $mailer->Subject = (string)$message->getSubject();
    }

    protected function setFrom(PHPMailer $mailer, AddressList $addresses)
    {
        foreach ($addresses as $address) {
            $mailer->setFrom($address->getEmail(), (string)$address->getName(), false);
            return;
        }

        throw new RuntimeException('Cannot send mail: missing From address.');
    }

    protected function appendAddresses(PHPMailer $mailer, $method, AddressList $addresses)
    {
        foreach ($addresses as $address) {
            /** @var AddressInterface $address */
            $mailer->{$method}($address->getEmail(), (string)$address->getName());
        }
    }

    protected function applyBody(PHPMailer $mailer, Message $message)
    {
        $body = $message->getBody();

        if ($body instanceof MimeMessage) {
            $this->applyMimeBody($mailer, $body);
            return;
        }

        $mailer->isHTML(false);
        $mailer->Body = (string)$body;
    }

    protected function applyMimeBody(PHPMailer $mailer, MimeMessage $body)
    {
        $plainBody = null;
        $htmlBody = null;
        $attachmentIndex = 0;

        foreach ($body->getParts() as $part) {
            $mimeType = strtolower((string)$part->type);
            $disposition = strtolower((string)$part->disposition);
            $isAttachment = in_array($disposition, [Mime::DISPOSITION_ATTACHMENT, Mime::DISPOSITION_INLINE], true);

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

    protected function addAttachment(PHPMailer $mailer, MimePart $part, $index, $mimeType, $disposition)
    {
        $content = $this->partContent($part);
        $name = (string)$part->filename;
        if ($name === '') {
            $name = 'attachment-' . $index;
        }

        $encoding = $this->toPhpmailerEncoding((string)$part->encoding);
        if ($mimeType === '') {
            $mimeType = Mime::TYPE_OCTETSTREAM;
        }

        if ($disposition === Mime::DISPOSITION_INLINE) {
            $cid = (string)$part->id;
            if ($cid === '') {
                $cid = 'inline-' . $index . '@myimouto.local';
            }
            $mailer->addStringEmbeddedImage($content, $cid, $name, $encoding, $mimeType);
            return;
        }

        $mailer->addStringAttachment($content, $name, $encoding, $mimeType, Mime::DISPOSITION_ATTACHMENT);
    }

    protected function partContent(MimePart $part)
    {
        $content = $part->getRawContent();
        if (is_resource($content)) {
            $data = stream_get_contents($content);
            $meta = stream_get_meta_data($content);
            if (!empty($meta['seekable'])) {
                rewind($content);
            }
            return (string)$data;
        }
        return (string)$content;
    }

    protected function toPhpmailerEncoding($encoding)
    {
        $encoding = strtolower(trim($encoding));

        switch ($encoding) {
            case Mime::ENCODING_QUOTEDPRINTABLE:
                return PHPMailer::ENCODING_QUOTED_PRINTABLE;
            case Mime::ENCODING_7BIT:
                return PHPMailer::ENCODING_7BIT;
            case Mime::ENCODING_8BIT:
                return PHPMailer::ENCODING_8BIT;
            case 'binary':
                return PHPMailer::ENCODING_BINARY;
            case Mime::ENCODING_BASE64:
            default:
                return PHPMailer::ENCODING_BASE64;
        }
    }
}
