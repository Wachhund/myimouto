<?php

declare(strict_types=1);

namespace Tests\Unit\PostReplacement;

use MyImouto\PostReplacement\StagingService;
use PHPUnit\Framework\TestCase;

final class StagingServiceTest extends TestCase
{
    public function testRejectsUnsupportedScheme(): void
    {
        self::assertFalse(StagingService::isSafeSourceUrl('ftp://example.com/file.jpg'));
    }

    public function testRejectsLocalhostTargets(): void
    {
        self::assertFalse(StagingService::isSafeSourceUrl('http://localhost/file.jpg'));
        self::assertFalse(StagingService::isSafeSourceUrl('http://127.0.0.1/file.jpg'));
        self::assertFalse(StagingService::isSafeSourceUrl('http://[::1]/file.jpg'));
        self::assertFalse(StagingService::isSafeSourceUrl('http://ip6-localhost/file.jpg'));
    }

    public function testRejectsPrivateIpv4Range(): void
    {
        self::assertFalse(StagingService::isSafeSourceUrl('http://10.1.2.3/file.jpg'));
    }

    public function testAllowsPublicIpv4(): void
    {
        self::assertTrue(StagingService::isSafeSourceUrl('https://1.1.1.1/file.jpg'));
    }

    public function testRejectsUnresolvedHostnames(): void
    {
        self::assertFalse(StagingService::isSafeSourceUrl('https://nonexistent.invalid/file.jpg'));
    }
}
