<?php

declare(strict_types=1);

namespace Tests\Unit\PostSearch;

use MyImouto\PostSearch\ApiContract;
use PHPUnit\Framework\TestCase;

final class ApiContractTest extends TestCase
{
    public function testBuildSearchEnvelopeAlwaysContainsStableTopLevelKeys(): void
    {
        $envelope = ApiContract::buildSearchEnvelope(
            [['id' => 1]],
            [
                'query' => 'rating:safe',
                'count' => 1,
                'page' => 2,
                'per_page' => 10,
                'api_version' => '2',
            ],
        );

        self::assertTrue($envelope['success']);
        self::assertSame('rating:safe', $envelope['query']);
        self::assertSame(1, $envelope['count']);
        self::assertSame(2, $envelope['page']);
        self::assertSame(10, $envelope['per_page']);
        self::assertSame([['id' => 1]], $envelope['posts']);
        self::assertSame([], $envelope['tags']);
        self::assertSame([], $envelope['pools']);
        self::assertSame([], $envelope['pool_posts']);
        self::assertSame([], $envelope['votes']);
        self::assertSame('2', $envelope['meta']['api_version']);
    }

    public function testBuildSearchEnvelopeNormalizesBatchDataCollections(): void
    {
        $envelope = ApiContract::buildSearchEnvelope(
            [],
            [
                'batch_data' => [
                    'tags' => ['a' => ['name' => 'tag_a']],
                    'pools' => [['id' => 1]],
                    'pool_posts' => [['post_id' => 10]],
                    'votes' => ['10' => 1],
                ],
            ],
        );

        self::assertSame([['name' => 'tag_a']], $envelope['tags']);
        self::assertSame([['id' => 1]], $envelope['pools']);
        self::assertSame([['post_id' => 10]], $envelope['pool_posts']);
        self::assertSame(['10' => 1], $envelope['votes']);
    }

    public function testBuildCountEnvelopeReturnsCountMetadataOnly(): void
    {
        $envelope = ApiContract::buildCountEnvelope('rating:safe', 25, [
            'api_version' => '2',
            'filter' => true,
        ]);

        self::assertTrue($envelope['success']);
        self::assertSame('rating:safe', $envelope['query']);
        self::assertSame(25, $envelope['count']);
        self::assertSame('2', $envelope['meta']['api_version']);
        self::assertTrue($envelope['meta']['filter']);
        self::assertSame(1, $envelope['meta']['response_version']);
    }
}
