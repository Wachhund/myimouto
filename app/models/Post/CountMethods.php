<?php

trait PostCountMethods
{
    public static function fast_count($tags = null)
    {
        # A small sanitation
        $tags = preg_replace('/ +/', ' ', trim($tags));
        $cache_version = (int) Rails::cache()->read('$cache_version');
        $key = ['post_count' => $tags, 'v' => $cache_version];

        $count = (int) Rails::cache()->fetch($key, function () use ($tags) {
            list($sql, $params) = Post::generate_sql($tags, ['count' => true]);
            array_unshift($params, $sql);
            return Post::countBySql($params);
        });

        return $count;

        # This is just too brittle, and hard to make work with other features that may
        # hide posts from the index.
        #      if tags.blank?
        #        return select_value_sql("SELECT row_count FROM table_data WHERE name = 'posts'").to_i
        #      else
        #        c = select_value_sql("SELECT post_count FROM tags WHERE name = ?", tags).to_i
        #        if c == 0
        #          return Post.count_by_sql(Post.generate_sql(tags, :count => true))
        #        else
        #          return c
        #        end
        #      end
    }

    public static function recalculate_row_count()
    {
        self::connection()->executeSql("UPDATE table_data SET row_count = (SELECT COUNT(*) FROM posts WHERE parent_id IS NULL AND status <> 'deleted') WHERE name = 'posts'");
    }

    public static function row_count()
    {
        $sql = "SELECT COUNT(*) FROM posts WHERE status <> 'deleted' AND NOT is_held AND status <> 'pending' AND is_shown_in_index";
        return current(self::connection()->executeSql($sql)->fetch());
    }

    public function increment_count()
    {
        self::connection()->executeSql("UPDATE table_data SET row_count = row_count + 1 WHERE name = 'posts'");
    }

    public function decrement_count()
    {
        self::connection()->executeSql("UPDATE table_data SET row_count = row_count - 1 WHERE name = 'posts'");
    }
}
