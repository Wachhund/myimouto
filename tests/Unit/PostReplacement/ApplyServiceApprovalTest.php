<?php

declare(strict_types=1);

/**
 * Unit tests for \MyImouto\PostReplacement\ApplyService::approve() covering approval, rejection,
 * cleanup, and edge-case paths.
 *
 * Because ApplyService depends on ActiveRecord models (\PostReplacement,
 * \User, \Post) and static helpers (StagingService, NotificationService)
 * that require the full framework + database, we define lightweight stubs
 * that replicate only the surface area touched by the approve() method.
 *
 * Stubs live in a dedicated bootstrap file to avoid namespace conflicts.
 * The class uses #[RunTestsInSeparateProcesses] so stub class definitions
 * never bleed into the main PHPUnit process that runs other tests.
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ApplyServiceApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        // Load stubs inside the subprocess — never in the parent scanner.
        require_once __DIR__ . '/ApplyServiceApprovalBootstrap.php';

        \MyImouto\PostReplacement\StagingService::reset();
        \MyImouto\PostReplacement\NotificationService::reset();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function makePendingReplacement(array $overrides = []): \PostReplacement
    {
        $r = new \PostReplacement();
        $r->status = \PostReplacement::STATUS_PENDING;
        $r->post = new \Post();

        foreach ($overrides as $key => $value) {
            $r->$key = $value;
        }

        return $r;
    }

    private function makeReviewer(int $id = 99): \User
    {
        return new \User($id);
    }

    // ── AC-1: Successful approval with staged upload payload ─────────

    public function testApproveSucceedsWithExplicitResolvedPayload(): void
    {
        $replacement = $this->makePendingReplacement();
        $reviewer = $this->makeReviewer();

        $resolved = [
            'path' => '/tmp/staged/image.jpg',
            'name' => 'image.jpg',
        ];

        $result = \MyImouto\PostReplacement\ApplyService::approve($replacement, $reviewer, 'looks good', $resolved);

        self::assertSame(\PostReplacement::STATUS_APPROVED, $result->status);
        self::assertSame(99, $result->reviewed_by_id);
        self::assertNotNull($result->reviewed_at);
        self::assertSame('looks good', $result->moderation_reason);
        self::assertSame('abc123def456', $result->replacement_md5);
        self::assertNull($result->replacement_file_path, 'Staged path should be cleared after approval');
        self::assertNull($result->replacement_file_name, 'Staged name should be cleared after approval');
        self::assertTrue($result->saved, 'Record should have been saved');
    }

    public function testApproveTriggersCleanupAndNotification(): void
    {
        $replacement = $this->makePendingReplacement();
        $reviewer = $this->makeReviewer();

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $reviewer, null, [
            'path' => '/tmp/staged/photo.png',
            'name' => 'photo.png',
        ]);

        self::assertContains('/tmp/staged/photo.png', \MyImouto\PostReplacement\StagingService::$cleanedPaths);
        self::assertCount(1, \MyImouto\PostReplacement\NotificationService::$emitted);
        self::assertSame($replacement, \MyImouto\PostReplacement\NotificationService::$emitted[0]);
    }

    public function testApproveNullModerationReasonNormalizesToNull(): void
    {
        $replacement = $this->makePendingReplacement();
        $reviewer = $this->makeReviewer();

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $reviewer, null, [
            'path' => '/tmp/staged/img.jpg',
            'name' => 'img.jpg',
        ]);

        self::assertNull($replacement->moderation_reason);
    }

    public function testApproveTrimsWhitespaceOnlyModerationReason(): void
    {
        $replacement = $this->makePendingReplacement();
        $reviewer = $this->makeReviewer();

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $reviewer, '   ', [
            'path' => '/tmp/staged/img.jpg',
            'name' => 'img.jpg',
        ]);

        self::assertNull($replacement->moderation_reason);
    }

    // ── AC-1b: Approval using replacement_file_path from record ──────

    public function testApproveSucceedsFromRecordFilePath(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'apply_test_');
        self::assertNotFalse($tmpFile);

        try {
            $replacement = $this->makePendingReplacement([
                'replacement_file_path' => $tmpFile,
                'replacement_file_name' => 'original_upload.jpg',
            ]);

            $result = \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());

            self::assertSame(\PostReplacement::STATUS_APPROVED, $result->status);
            self::assertTrue($result->saved);
        } finally {
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    // ── AC-2: URL-based approval scenarios ───────────────────────────

    public function testApproveRejectsSourceUrlWithoutPreload(): void
    {
        $replacement = $this->makePendingReplacement([
            'source_url' => 'https://example.com/image.jpg',
            'replacement_file_path' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source URL replacement must be preloaded before approval');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());
    }

    public function testApproveSucceedsForSourceUrlWhenPreloaded(): void
    {
        $replacement = $this->makePendingReplacement([
            'source_url' => 'https://example.com/image.jpg',
        ]);

        $resolved = [
            'path' => '/tmp/staged/preloaded.jpg',
            'name' => 'preloaded.jpg',
        ];

        $result = \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, $resolved);

        self::assertSame(\PostReplacement::STATUS_APPROVED, $result->status);
        self::assertTrue($result->saved);
    }

    // ── AC-2b: No payload at all ─────────────────────────────────────

    public function testApproveRejectsWhenNoPayloadAvailable(): void
    {
        $replacement = $this->makePendingReplacement([
            'replacement_file_path' => null,
            'source_url' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No replacement payload available');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());
    }

    // ── AC-3: Failure path cleanup ───────────────────────────────────

    public function testApproveCleansStagedPayloadWhenReplaceFileFails(): void
    {
        $post = new \Post();
        $post->replaceShouldSucceed = false;

        $replacement = $this->makePendingReplacement();
        $replacement->post = $post;

        $resolved = [
            'path' => '/tmp/staged/will_fail.jpg',
            'name' => 'will_fail.jpg',
        ];

        try {
            \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, $resolved);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('replace failed', $e->getMessage());
        }

        // Staged file should be cleaned up on error for non-record payloads.
        self::assertContains('/tmp/staged/will_fail.jpg', \MyImouto\PostReplacement\StagingService::$cleanedPaths);

        // Status should NOT have been updated since the operation failed.
        self::assertSame(\PostReplacement::STATUS_PENDING, $replacement->status);
        self::assertFalse($replacement->saved);
    }

    public function testApproveDoesNotCleanRecordPayloadOnFailure(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'apply_test_');
        self::assertNotFalse($tmpFile);

        try {
            $post = new \Post();
            $post->replaceShouldSucceed = false;

            $replacement = $this->makePendingReplacement([
                'replacement_file_path' => $tmpFile,
                'replacement_file_name' => 'record_upload.jpg',
            ]);
            $replacement->post = $post;

            try {
                \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());
                self::fail('Expected RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('replace failed', $e->getMessage());
            }

            // When the payload came from the record (from_record=true),
            // the error handler should NOT clean it up.
            self::assertNotContains($tmpFile, \MyImouto\PostReplacement\StagingService::$cleanedPaths);
        } finally {
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    public function testApproveRethrowsExceptionAfterCleanup(): void
    {
        $post = new \Post();
        $post->replaceShouldSucceed = false;

        $replacement = $this->makePendingReplacement();
        $replacement->post = $post;

        $this->expectException(\RuntimeException::class);

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, [
            'path' => '/tmp/staged/rethrow_test.jpg',
            'name' => 'rethrow_test.jpg',
        ]);
    }

    // ── AC-3b: Save failure triggers cleanup ─────────────────────────

    public function testApproveCleanupWhenSaveFails(): void
    {
        $replacement = $this->makePendingReplacement();
        $replacement->saveShouldFail = true;

        try {
            \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, [
                'path' => '/tmp/staged/save_fail.jpg',
                'name' => 'save_fail.jpg',
            ]);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('save failed', $e->getMessage());
        }

        // Staged payload should be cleaned up on save failure.
        self::assertContains('/tmp/staged/save_fail.jpg', \MyImouto\PostReplacement\StagingService::$cleanedPaths);
    }

    // ── Status guards ────────────────────────────────────────────────

    public function testApproveRejectsNonPendingStatus(): void
    {
        $replacement = $this->makePendingReplacement();
        $replacement->status = \PostReplacement::STATUS_APPROVED;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replacement is not in pending state');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, [
            'path' => '/tmp/staged/already_approved.jpg',
            'name' => 'already_approved.jpg',
        ]);
    }

    public function testApproveRejectsRejectedStatus(): void
    {
        $replacement = $this->makePendingReplacement();
        $replacement->status = \PostReplacement::STATUS_REJECTED;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replacement is not in pending state');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());
    }

    public function testApproveRejectsDeletedStatus(): void
    {
        $replacement = $this->makePendingReplacement();
        $replacement->status = \PostReplacement::STATUS_DELETED;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replacement is not in pending state');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer());
    }

    // ── Post-not-found guard ─────────────────────────────────────────

    public function testApproveRejectsWhenPostNotFound(): void
    {
        $replacement = $this->makePendingReplacement();
        $replacement->post = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target post not found');

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, [
            'path' => '/tmp/staged/no_post.jpg',
            'name' => 'no_post.jpg',
        ]);
    }

    // ── AC-4: resolveReplacementFile defaults ────────────────────────

    public function testResolvedPayloadNameDefaultsToBasename(): void
    {
        $replacement = $this->makePendingReplacement();

        $resolved = [
            'path' => '/tmp/staged/some_deep/nested/file.png',
            // 'name' deliberately omitted
        ];

        $result = \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, $resolved);

        self::assertSame(\PostReplacement::STATUS_APPROVED, $result->status);
        self::assertTrue($result->saved);
    }

    // ── AC-5: Reviewer ID is correctly recorded ──────────────────────

    public function testReviewerIdRecordedCorrectly(): void
    {
        $replacement = $this->makePendingReplacement();
        $reviewer = $this->makeReviewer(42);

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $reviewer, 'approved by mod', [
            'path' => '/tmp/staged/reviewer_test.jpg',
            'name' => 'reviewer_test.jpg',
        ]);

        self::assertSame(42, $replacement->reviewed_by_id);
    }

    // ── md5 is captured from post after replacement ──────────────────

    public function testMd5CapturedFromPostAfterReplacement(): void
    {
        $post = new \Post();
        $post->md5 = 'newmd5hash999';

        $replacement = $this->makePendingReplacement();
        $replacement->post = $post;

        \MyImouto\PostReplacement\ApplyService::approve($replacement, $this->makeReviewer(), null, [
            'path' => '/tmp/staged/md5_test.jpg',
            'name' => 'md5_test.jpg',
        ]);

        self::assertSame('newmd5hash999', $replacement->replacement_md5);
    }
}
