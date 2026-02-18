<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use MyImouto\Mail\Address as MyImoutoAddress;
use MyImouto\Mail\Headers as MyImoutoHeaders;
use MyImouto\Mail\Message as MyImoutoMessage;
use MyImouto\Mail\Transport\File as MyImoutoFileTransport;
use MyImouto\Mail\Transport\Smtp as MyImoutoSmtpTransport;
use MyImouto\Mime\Message as MyImoutoMimeMessage;
use MyImouto\Mime\Part as MyImoutoMimePart;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend\Mail\Address;
use Zend\Mail\Headers;
use Zend\Mail\Message;
use Zend\Mail\Transport\File;
use Zend\Mail\Transport\FileOptions;
use Zend\Mail\Transport\Smtp;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part;

final class NamespaceDecouplingTest extends TestCase
{
    /** @var string|null */
    private $tmpDir = null;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myimouto_mail_ns_' . bin2hex(random_bytes(4));
        if (!mkdir($this->tmpDir, 0777, true) && !is_dir($this->tmpDir)) {
            throw new RuntimeException('Failed to create temp test directory');
        }
    }

    protected function tearDown(): void
    {
        if (!$this->tmpDir) {
            return;
        }

        if (is_file($this->tmpDir . DIRECTORY_SEPARATOR . 'mail.tmp')) {
            @unlink($this->tmpDir . DIRECTORY_SEPARATOR . 'mail.tmp');
        }
        @rmdir($this->tmpDir);
    }

    public function test_zend_classes_are_wrappers_for_myimouto_canonical_runtime(): void
    {
        $this->assertInstanceOf(MyImoutoMessage::class, new Message());
        $this->assertInstanceOf(MyImoutoAddress::class, new Address('test@example.test'));
        $this->assertInstanceOf(MyImoutoHeaders::class, new Headers());
        $this->assertInstanceOf(MyImoutoFileTransport::class, new File());
        $this->assertInstanceOf(MyImoutoSmtpTransport::class, new Smtp());
        $this->assertInstanceOf(MyImoutoMimeMessage::class, new MimeMessage());
        $this->assertInstanceOf(MyImoutoMimePart::class, new Part('payload'));
    }

    public function test_zend_file_transport_wrapper_delivers_with_mime_body(): void
    {
        $message = new Message();
        $message->setFrom('sender@example.test', 'Sender');
        $message->addTo('receiver@example.test', 'Receiver');
        $message->setSubject('Namespace shim test');

        $mime = new MimeMessage();
        $part = new Part('hello from canonical runtime');
        $part->type = 'text/plain';
        $mime->addPart($part);
        $message->setBody($mime);

        $this->assertSame('multipart/mixed', $message->getHeaders()->get('content-type')->getType());

        $options = new FileOptions([
            'path' => $this->tmpDir,
            'callback' => static function () {
                return 'mail.tmp';
            },
        ]);

        $transport = new File();
        $transport->setOptions($options);
        $transport->send($message);

        $mailFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'mail.tmp';
        $this->assertFileExists($mailFile);
        $content = file_get_contents($mailFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Subject: Namespace shim test', $content);
        $this->assertStringContainsString('hello from canonical runtime', $content);
    }
}
