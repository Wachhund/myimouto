<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!function_exists('current_user')) {
    function current_user()
    {
        return $GLOBALS['__qa_current_user'] ?? null;
    }
}

require_once dirname(__DIR__, 2) . '/app/models/Post/ApiMethods.php';

final class PostApiMethodsHarness
{
    use PostApiMethods;
}

final class PostApiMethodsUserStub
{
    /** @var bool */
    private $isMod;

    public function __construct(bool $isMod)
    {
        $this->isMod = $isMod;
    }

    public function is_mod_or_higher(): bool
    {
        return $this->isMod;
    }
}

final class PostApiMethodsFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__qa_current_user']);
    }

    public function test_non_array_input_is_normalized_to_empty_array(): void
    {
        $params = 'bad-input';
        PostApiMethodsHarness::filter_api_changes($params);
        $this->assertSame([], $params);
    }

    public function test_non_mod_user_cannot_change_protected_or_moderation_fields(): void
    {
        $GLOBALS['__qa_current_user'] = new PostApiMethodsUserStub(false);

        $params = [
            'id' => 100,
            'user_id' => 1,
            'md5' => 'abc',
            'file_size' => 123,
            'frames' => 'raw',
            'frames_warehoused' => 'raw2',
            'status' => 'active',
            'is_held' => true,
            'is_shown_in_index' => false,
            'is_note_locked' => true,
            'is_rating_locked' => true,
            'source' => 'https://example.test/src',
            'tags' => 'safe_tag',
            'rating' => 's',
        ];

        PostApiMethodsHarness::filter_api_changes($params);

        $this->assertArrayNotHasKey('id', $params);
        $this->assertArrayNotHasKey('user_id', $params);
        $this->assertArrayNotHasKey('md5', $params);
        $this->assertArrayNotHasKey('file_size', $params);
        $this->assertArrayNotHasKey('frames', $params);
        $this->assertArrayNotHasKey('frames_warehoused', $params);
        $this->assertArrayNotHasKey('status', $params);
        $this->assertArrayNotHasKey('is_held', $params);
        $this->assertArrayNotHasKey('is_shown_in_index', $params);
        $this->assertArrayNotHasKey('is_note_locked', $params);
        $this->assertArrayNotHasKey('is_rating_locked', $params);

        $this->assertSame('https://example.test/src', $params['source']);
        $this->assertSame('safe_tag', $params['tags']);
        $this->assertSame('s', $params['rating']);
    }

    public function test_mod_user_keeps_moderation_fields_but_not_always_blocked_fields(): void
    {
        $GLOBALS['__qa_current_user'] = new PostApiMethodsUserStub(true);

        $params = [
            'status' => 'deleted',
            'is_held' => true,
            'is_shown_in_index' => false,
            'is_note_locked' => true,
            'is_rating_locked' => true,
            'md5' => 'must-be-removed',
            'updater_user_id' => 99,
            'source' => 'https://example.test/mod',
        ];

        PostApiMethodsHarness::filter_api_changes($params);

        $this->assertSame('deleted', $params['status']);
        $this->assertTrue($params['is_held']);
        $this->assertFalse($params['is_shown_in_index']);
        $this->assertTrue($params['is_note_locked']);
        $this->assertTrue($params['is_rating_locked']);
        $this->assertSame('https://example.test/mod', $params['source']);

        $this->assertArrayNotHasKey('md5', $params);
        $this->assertArrayNotHasKey('updater_user_id', $params);
    }
}
