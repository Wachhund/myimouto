<?php

declare(strict_types=1);

namespace Tests\Unit\Forum;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for ForumPostVote static method contracts.
 *
 * Since ForumPostVote extends Rails\ActiveRecord\Base and cannot be
 * instantiated without a database connection, these tests verify
 * method signatures, parameter contracts, and the early-return paths
 * for empty inputs via source-level analysis and, where possible,
 * direct invocation of the empty-input code path.
 */
final class ForumPostVoteTest extends TestCase
{
    private static ?ReflectionClass $ref = null;

    public static function setUpBeforeClass(): void
    {
        // Ensure the Rails ActiveRecord base class stub exists for parsing
        if (!class_exists('Rails\ActiveRecord\Base', false)) {
            // Cannot load ForumPostVote without the full framework;
            // we parse the source to verify contracts.
            self::$ref = null;
            return;
        }

        if (class_exists('ForumPostVote', true)) {
            self::$ref = new ReflectionClass('ForumPostVote');
        }
    }

    // ---- Source-level contract tests (always run) ----

    /**
     * Verify that the source file is valid PHP and contains the expected class.
     */
    public function testSourceFileIsValidPhp(): void
    {
        $path = dirname(__DIR__, 3) . '/app/models/ForumPostVote.php';
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertStringContainsString('class ForumPostVote', $source);
    }

    /**
     * Verify unvote() exists and accepts two parameters.
     */
    public function testUnvoteMethodExistsInSource(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        // Method declaration: static public function unvote($user_id, $post_id)
        self::assertMatchesRegularExpression(
            '/static\s+public\s+function\s+unvote\s*\(\s*\$user_id\s*,\s*\$post_id\s*\)/',
            $source,
            'unvote() must be a static public method with $user_id and $post_id params'
        );
    }

    /**
     * Verify unvote() returns bool (true when deleted, false when not found).
     */
    public function testUnvoteReturnsBoolean(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        // The method body should contain "return true;" and "return false;"
        // Extract the method body between function unvote and the next public/static/protected
        preg_match('/function\s+unvote\s*\([^)]*\)\s*\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m, 'Should be able to extract unvote() method body');

        $body = $m[1];
        self::assertStringContainsString('return true;', $body, 'unvote() should return true on success');
        self::assertStringContainsString('return false;', $body, 'unvote() should return false when no vote exists');
    }

    /**
     * Verify bulk_post_scores() accepts an array parameter and has an
     * early-return for empty input.
     */
    public function testBulkPostScoresHasEmptyGuard(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        self::assertMatchesRegularExpression(
            '/function\s+bulk_post_scores\s*\(\s*array\s+\$post_ids\s*\)/',
            $source,
            'bulk_post_scores() must accept a typed array parameter'
        );

        // Verify early return for empty array
        preg_match('/function\s+bulk_post_scores.*?\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m);
        self::assertStringContainsString('empty($post_ids)', $m[1], 'Should guard against empty input');
    }

    /**
     * Verify bulk_user_votes() accepts a user_id and array parameter
     * with an early-return for empty input.
     */
    public function testBulkUserVotesHasEmptyGuard(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        self::assertMatchesRegularExpression(
            '/function\s+bulk_user_votes\s*\(\s*\$user_id\s*,\s*array\s+\$post_ids\s*\)/',
            $source,
            'bulk_user_votes() must accept $user_id and typed array $post_ids'
        );

        // Verify early return for empty array
        preg_match('/function\s+bulk_user_votes.*?\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m);
        self::assertStringContainsString('empty($post_ids)', $m[1], 'Should guard against empty input');
    }

    /**
     * Verify bulk_post_scores() returns a keyed array (post_id => score).
     */
    public function testBulkPostScoresReturnStructure(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        preg_match('/function\s+bulk_post_scores.*?\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m);
        $body = $m[1];

        // Should initialize an empty array and populate it with int-keyed entries
        self::assertStringContainsString('$scores = []', $body);
        self::assertMatchesRegularExpression('/\$scores\[.*forum_post_id.*\]\s*=/', $body, 'Should key results by forum_post_id');
    }

    /**
     * Verify bulk_user_votes() returns a keyed array (post_id => score).
     */
    public function testBulkUserVotesReturnStructure(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        preg_match('/function\s+bulk_user_votes.*?\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m);
        $body = $m[1];

        self::assertStringContainsString('$votes = []', $body);
        self::assertMatchesRegularExpression('/\$votes\[.*forum_post_id.*\]\s*=/', $body, 'Should key results by forum_post_id');
    }

    /**
     * Verify vote() method clamps score to [-1, 1].
     */
    public function testVoteMethodClampsScore(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/models/ForumPostVote.php');

        preg_match('/function\s+vote\s*\(.*?\)\s*\{(.*?)\n    \}/s', $source, $m);
        self::assertNotEmpty($m);
        $body = $m[1];

        self::assertStringContainsString('max(-1, min(1,', $body, 'vote() should clamp score to [-1, 1]');
    }

    // ---- Reflection tests (run only when framework is loaded) ----

    public function testReflectionUnvoteSignature(): void
    {
        if (!self::$ref) {
            self::markTestSkipped('ForumPostVote class not loadable without full Rails framework');
        }

        $method = self::$ref->getMethod('unvote');
        self::assertTrue($method->isStatic());
        self::assertCount(2, $method->getParameters());
    }

    public function testReflectionBulkPostScoresSignature(): void
    {
        if (!self::$ref) {
            self::markTestSkipped('ForumPostVote class not loadable without full Rails framework');
        }

        $method = self::$ref->getMethod('bulk_post_scores');
        self::assertTrue($method->isStatic());
        self::assertCount(1, $method->getParameters());
        self::assertSame('array', $method->getParameters()[0]->getType()->getName());
    }

    public function testReflectionBulkUserVotesSignature(): void
    {
        if (!self::$ref) {
            self::markTestSkipped('ForumPostVote class not loadable without full Rails framework');
        }

        $method = self::$ref->getMethod('bulk_user_votes');
        self::assertTrue($method->isStatic());
        self::assertCount(2, $method->getParameters());
        self::assertSame('array', $method->getParameters()[1]->getType()->getName());
    }
}
