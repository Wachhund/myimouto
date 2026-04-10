<?php

class Report
{
    public static function usage_by_user($table_name, $start, $stop, $limit, $level, array $conds = [], array $params = [], $column = 'created_at')
    {
        $limit = max(1, min(100, (int) $limit));
        $connection = User::connection();

        $conds[] = "{$table_name}.{$column} BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $stop;

        if ($level !== null && (int) $level !== 0) {
            $conds[] = "users.level = ?";
            $params[] = (int) $level;
        }

        $sql = "SELECT users.id AS id, COUNT(*) AS change_count
                FROM {$table_name}
                JOIN users ON users.id = {$table_name}.user_id
                WHERE " . implode(" AND ", $conds) . "
                GROUP BY users.id
                ORDER BY change_count DESC
                LIMIT {$limit}";

        $users = call_user_func_array([$connection, 'select'], array_merge([$sql], $params));
        if (!$users) {
            $users = [];
        }

        $user_ids = [];
        foreach ($users as &$user) {
            $user['id'] = (int) $user['id'];
            $user['change_count'] = (int) $user['change_count'];
            $user['user'] = User::where('id = ?', $user['id'])->first();
            $user['name'] = $user['user'] ? $user['user']->name : User::find_name($user['id']);
            $user_ids[] = $user['id'];
        }
        unset($user);

        $other_conds = $conds;
        $other_params = $params;
        if ($user_ids) {
            $other_conds[] = "users.id NOT IN (?)";
            $other_params[] = $user_ids;
        }

        $other_sql = "SELECT COUNT(*)
                      FROM {$table_name}
                      JOIN users ON users.id = {$table_name}.user_id
                      WHERE " . implode(" AND ", $other_conds);
        $other_count = (int) call_user_func_array([$connection, 'selectValue'], array_merge([$other_sql], $other_params));

        $users[] = [
            'id' => null,
            'change_count' => $other_count,
            'user' => null,
            'name' => 'Other',
        ];

        return self::add_sum($users);
    }

    public static function tag_updates($start, $stop, $limit, $level)
    {
        $users = self::usage_by_user('post_tag_histories', $start, $stop, $limit, $level);

        $bottom = array_pop($users);
        foreach ($users as &$user) {
            $upload_count = Post::where("user_id = ? AND created_at BETWEEN ? AND ?", $user['id'], $start, $stop)->count();
            $user['change_count'] = max(0, (int) $user['change_count'] - (int) $upload_count);
        }
        unset($user);

        usort($users, function ($a, $b) {
            return (int) $b['change_count'] - (int) $a['change_count'];
        });

        $users[] = $bottom;
        return self::add_sum($users);
    }

    public static function post_uploads($start, $stop, $limit, $level)
    {
        return self::usage_by_user('posts', $start, $stop, $limit, $level);
    }

    public static function wiki_updates($start, $stop, $limit, $level)
    {
        return self::usage_by_user('wiki_page_versions', $start, $stop, $limit, $level);
    }

    public static function note_updates($start, $stop, $limit, $level)
    {
        return self::usage_by_user('note_versions', $start, $stop, $limit, $level);
    }

    public static function votes($start, $stop, $limit, $level)
    {
        $users = self::usage_by_user('post_votes', $start, $stop, $limit, $level, ['score > 0'], [], 'updated_at');
        $connection = User::connection();

        $known_user_ids = [];
        foreach ($users as $user) {
            if ($user['id']) {
                $known_user_ids[] = (int) $user['id'];
            }
        }

        foreach ($users as &$user) {
            $conds = ["updated_at BETWEEN ? AND ?"];
            $params = [$start, $stop];

            if ($user['id']) {
                $conds[] = "user_id = ?";
                $params[] = (int) $user['id'];
            } elseif ($known_user_ids) {
                $conds[] = "user_id NOT IN (?)";
                $params[] = $known_user_ids;
            }

            $sql = "SELECT COUNT(score) AS sum, score
                    FROM post_votes
                    WHERE " . implode(" AND ", $conds) . "
                    GROUP BY score";

            $votes = call_user_func_array([$connection, 'select'], array_merge([$sql], $params));

            $user['votes'] = [];
            if ($votes) {
                foreach ($votes as $vote) {
                    $score = (int) $vote['score'];
                    $user['votes'][$score] = (int) $vote['sum'];
                }
            }
        }
        unset($user);

        return $users;
    }

    public static function add_sum(array $users)
    {
        $sum = 0;
        foreach ($users as $user) {
            $sum += (int) $user['change_count'];
        }

        foreach ($users as &$user) {
            $user['sum'] = (float) $sum;
        }
        unset($user);

        return $users;
    }
}
