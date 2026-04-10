<?php

declare(strict_types=1);

namespace MyImouto\PostReplacement;

/**
 * Stub StagingService for ApplyServiceApprovalTest.
 *
 * Records cleanup calls instead of performing real filesystem operations.
 */
class StagingService
{
    /** @var list<string> Paths passed to cleanup() during the test run */
    public static array $cleanedPaths = [];

    public static function cleanup($path): void
    {
        self::$cleanedPaths[] = (string) $path;
    }

    public static function reset(): void
    {
        self::$cleanedPaths = [];
    }
}

/**
 * Stub NotificationService for ApplyServiceApprovalTest.
 *
 * Records emitted notifications instead of sending real dmails.
 */
class NotificationService
{
    /** @var list<\PostReplacement> Replacements passed to emitModerationOutcome */
    public static array $emitted = [];

    public static function emitModerationOutcome(\PostReplacement $replacement): void
    {
        self::$emitted[] = $replacement;
    }

    public static function reset(): void
    {
        self::$emitted = [];
    }
}
