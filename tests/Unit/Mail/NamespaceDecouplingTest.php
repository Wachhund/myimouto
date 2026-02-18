<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NamespaceDecouplingTest extends TestCase
{
    /** @var string */
    private $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myimouto_mail_ns_' . bin2hex(random_bytes(4));
        if (!mkdir($this->tmpDir, 0777, true) && !is_dir($this->tmpDir)) {
            throw new RuntimeException('Failed to create temp test directory');
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpDir . DIRECTORY_SEPARATOR . 'mail.tmp')) {
            @unlink($this->tmpDir . DIRECTORY_SEPARATOR . 'mail.tmp');
        }
        @rmdir($this->tmpDir);
    }

    public function test_zend_classes_are_wrappers_for_myimouto_canonical_runtime(): void
    {
        $this->assertInstanceOf(MyImouto\Mail\Message::class, new Zend\Mail\Message());
        $this->assertInstanceOf(MyImouto\Mail\Address::class, new Zend\Mail\Address('test@example.test'));
        $this->assertInstanceOf(MyImouto\Mail\Headers::class, new Zend\Mail\Headers());
        $this->assertInstanceOf(MyImouto\Mail\Transport\File::class, new Zend\Mail\Transport\File());
        $this->assertInstanceOf(MyImouto\Mail\Transport\Smtp::class, new Zend\Mail\Transport\Smtp());
        $this->assertInstanceOf(MyImouto\Mime\Message::class, new Zend\Mime\Message());
        $this->assertInstanceOf(MyImouto\Mime\Part::class, new Zend\Mime\Part('payload'));
    }

    public function test_zend_file_transport_wrapper_delivers_with_mime_body(): void
    {
        $message = new Zend\Mail\Message();
        $message->setFrom('sender@example.test', 'Sender');
        $message->addTo('receiver@example.test', 'Receiver');
        $message->setSubject('Namespace shim test');

        $mime = new Zend\Mime\Message();
        $part = new Zend\Mime\Part('hello from canonical runtime');
        $part->type = 'text/plain';
        $mime->addPart($part);
        $message->setBody($mime);

        $this->assertSame('multipart/mixed', $message->getHeaders()->get('content-type')->getType());

        $options = new Zend\Mail\Transport\FileOptions([
            'path' => $this->tmpDir,
            'callback' => static function () {
                return 'mail.tmp';
            },
        ]);

        $transport = new Zend\Mail\Transport\File();
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

