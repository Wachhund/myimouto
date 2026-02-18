<?php

declare(strict_types=1);

namespace Rails\ActionView {
    class Helper
    {
        public function h($value)
        {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        public function u($value)
        {
            return rawurlencode((string)$value);
        }

        public function params()
        {
            return (object) ['tags' => 'safe_query'];
        }

        public function escapeJavascript($value)
        {
            return addslashes((string)$value);
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    if (!function_exists('CONFIG')) {
        function CONFIG()
        {
            if (!isset($GLOBALS['__qa_config'])) {
                $GLOBALS['__qa_config'] = (object) [
                    'enable_artists' => false,
                    'tag_order' => ['general', 'artist', 'copyright', 'character', 'species', 'meta'],
                ];
            }
            return $GLOBALS['__qa_config'];
        }
    }

    if (!function_exists('current_user')) {
        function current_user()
        {
            return $GLOBALS['__qa_current_user'] ?? null;
        }
    }

    if (!class_exists('Tag', false)) {
        class Tag
        {
            /** @var array<int, object> */
            public static $rows = [];

            public static function where($unusedSql, $unusedNames)
            {
                return new TagHelperEscapingFakeQuery(self::$rows);
            }
        }
    }

    final class TagHelperEscapingFakeQuery
    {
        /** @var array<int, object> */
        private $rows;

        /**
         * @param array<int, object> $rows
         */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function select($unused)
        {
            return $this;
        }

        public function order($unused)
        {
            return $this;
        }

        public function take()
        {
            return $this;
        }

        public function reduce(array $initial, callable $callback): array
        {
            $result = $initial;
            foreach ($this->rows as $row) {
                $result = $callback($result, $row);
            }
            return $result;
        }
    }

    final class TagHelperEscapingUserStub
    {
        public function is_privileged_or_higher(): bool
        {
            return false;
        }
    }

    require_once dirname(__DIR__, 2) . '/app/helpers/TagHelper.php';

    final class TagHelperEscapingTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__qa_current_user'] = new TagHelperEscapingUserStub();
        }

        protected function tearDown(): void
        {
            Tag::$rows = [];
            unset($GLOBALS['__qa_current_user']);
        }

        public function test_tag_links_escapes_malicious_name_and_sanitizes_tag_type(): void
        {
            $maliciousName = '"><img src=x onerror=alert(1)>';
            $maliciousType = 'general" onclick="alert(1)';

            Tag::$rows = [
                (object) [
                    'type_name' => $maliciousType,
                    'name' => $maliciousName,
                    'post_count' => 7,
                    'id' => 42,
                ],
            ];

            $helper = new TagHelper();
            $html = $helper->tag_links(['dummy_tag']);

            $this->assertStringContainsString('tag-type-generalonclickalert1', $html);
            $this->assertStringContainsString('data-type="generalonclickalert1"', $html);
            $this->assertStringContainsString('data-name="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"', $html);
            $this->assertStringContainsString(
                'href="/wiki/show?title=%22%3E%3Cimg%20src%3Dx%20onerror%3Dalert%281%29%3E"',
                $html
            );
            $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
            $this->assertStringNotContainsString('data-type="general" onclick="alert(1)"', $html);
        }
    }
}
