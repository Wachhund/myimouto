<?php

declare(strict_types=1);

/**
 * Bootstrap stubs for ApplyServiceApprovalTest.
 *
 * Defines lightweight stand-ins for the ActiveRecord models and service
 * classes that ApplyService::approve() depends on, so the test can run
 * in CI without a database, framework boot, or network access.
 *
 * This file MUST be loaded before the Composer autoloader resolves the
 * real MyImouto\PostReplacement\StagingService and NotificationService.
 * The test class uses #[RunTestsInSeparateProcesses] to ensure isolation.
 */

// ── Error bag (stands in for Rails\ActiveRecord\Errors) ──────────────

if (!class_exists('ApplyServiceTestErrorBag', false)) {
    final class ApplyServiceTestErrorBag
    {
        /** @var string[] */
        private array $messages = [];

        public function add(string $attr, string $msg): void
        {
            $this->messages[] = "$attr $msg";
        }

        public function fullMessages(string $separator = ', '): string
        {
            return implode($separator, $this->messages);
        }
    }
}

// ── PostReplacement stub ─────────────────────────────────────────────

if (!class_exists('PostReplacement', false)) {
    class PostReplacement
    {
        public const STATUS_PENDING = 'pending';
        public const STATUS_APPROVED = 'approved';
        public const STATUS_REJECTED = 'rejected';
        public const STATUS_DELETED = 'deleted';

        /** @var int */
        public $id = 1;

        /** @var int */
        public $post_id = 100;

        /** @var int */
        public $creator_id = 5;

        /** @var int|null */
        public $reviewed_by_id;

        /** @var string|null */
        public $reviewed_at;

        /** @var string|null */
        public $status;

        /** @var string|null */
        public $reason;

        /** @var string|null */
        public $moderation_reason;

        /** @var string|null */
        public $source_url;

        /** @var string|null */
        public $replacement_file_path;

        /** @var string|null */
        public $replacement_file_name;

        /** @var string|null */
        public $replacement_md5;

        /** @var object|null Post stub or null */
        public $post;

        /** @var bool Controls whether save() returns true */
        public bool $saveShouldFail = false;

        /** @var bool Set to true after a successful save() */
        public bool $saved = false;

        /** @var ApplyServiceTestErrorBag */
        private ApplyServiceTestErrorBag $errorBag;

        public function __construct()
        {
            $this->errorBag = new ApplyServiceTestErrorBag();
        }

        public function errors(): ApplyServiceTestErrorBag
        {
            return $this->errorBag;
        }

        public function save(): bool
        {
            if ($this->saveShouldFail) {
                $this->errorBag->add('base', 'save failed');
                return false;
            }
            $this->saved = true;
            return true;
        }
    }
}

// ── User stub ────────────────────────────────────────────────────────

if (!class_exists('User', false)) {
    class User
    {
        /** @var int */
        public $id;

        public function __construct(int $id = 1)
        {
            $this->id = $id;
        }
    }
}

// ── Post stub ────────────────────────────────────────────────────────

if (!class_exists('Post', false)) {
    class Post
    {
        /** @var string */
        public $md5 = 'abc123def456';

        /** @var bool Controls whether replace_file_from_path() succeeds */
        public bool $replaceShouldSucceed = true;

        /** @var ApplyServiceTestErrorBag */
        private ApplyServiceTestErrorBag $errorBag;

        public function __construct()
        {
            $this->errorBag = new ApplyServiceTestErrorBag();
        }

        public function errors(): ApplyServiceTestErrorBag
        {
            return $this->errorBag;
        }

        /**
         * @param array<string, list<string>> $context
         */
        public function replace_file_from_path(string $path, string $name, array &$context): bool
        {
            if (!$this->replaceShouldSucceed) {
                $this->errorBag->add('file', 'replace failed');
                return false;
            }

            $context['new_paths'][] = $path . '.converted';
            return true;
        }
    }
}

// ── Load StagingService & NotificationService stubs ──────────────────
// These replace the real implementations for testing purposes.
// The stubs file uses a namespace block, so it must be a separate file.

require_once __DIR__ . '/ApplyServiceApprovalStubs.php';
