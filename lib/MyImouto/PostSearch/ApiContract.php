<?php

namespace MyImouto\PostSearch;

class ApiContract
{
    public static function buildSearchEnvelope(array $posts, array $options = [])
    {
        $batchData = isset($options['batch_data']) && is_array($options['batch_data']) ? $options['batch_data'] : [];
        $query = isset($options['query']) ? (string) $options['query'] : '';
        $count = isset($options['count']) ? (int) $options['count'] : count($posts);
        $page = isset($options['page']) ? (int) $options['page'] : 1;
        $perPage = isset($options['per_page']) ? (int) $options['per_page'] : count($posts);
        $apiVersion = isset($options['api_version']) ? (string) $options['api_version'] : '1';

        return [
            'success' => true,
            'query' => $query,
            'count' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'meta' => [
                'api_version' => $apiVersion,
                'response_version' => 1,
            ],
            'posts' => $posts,
            'tags' => self::normalizeArrayField($batchData, 'tags'),
            'pools' => self::normalizeArrayField($batchData, 'pools'),
            'pool_posts' => self::normalizeArrayField($batchData, 'pool_posts'),
            'votes' => self::normalizeMapField($batchData, 'votes'),
        ];
    }

    public static function buildCountEnvelope($query, $count, array $options = [])
    {
        $apiVersion = isset($options['api_version']) ? (string) $options['api_version'] : '1';
        $filter = !empty($options['filter']);

        return [
            'success' => true,
            'query' => (string) $query,
            'count' => (int) $count,
            'meta' => [
                'api_version' => $apiVersion,
                'response_version' => 1,
                'filter' => $filter,
            ],
        ];
    }

    private static function normalizeArrayField(array $batchData, $field)
    {
        if (!isset($batchData[$field]) || !is_array($batchData[$field])) {
            return [];
        }

        return array_values($batchData[$field]);
    }

    private static function normalizeMapField(array $batchData, $field)
    {
        if (!isset($batchData[$field]) || !is_array($batchData[$field])) {
            return [];
        }

        return $batchData[$field];
    }
}
