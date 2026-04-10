<?php

class PostSet extends Rails\ActiveRecord\Base
{
    public const MAINTAINER_STATUS_PENDING = 'pending';
    public const MAINTAINER_STATUS_APPROVED = 'approved';
    public const MAINTAINER_STATUS_BLOCKED = 'blocked';

    protected function associations()
    {
        return [
            'belongs_to' => [
                'creator' => ['class_name' => 'User', 'foreign_key' => 'creator_id'],
            ],
            'has_many' => [
                'post_set_posts' => ['class_name' => 'PostSetPost', 'dependent' => 'delete_all'],
                'post_set_maintainers' => ['class_name' => 'PostSetMaintainer', 'dependent' => 'delete_all'],
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'before_validation' => ['normalize_fields'],
        ];
    }

    protected function validations()
    {
        return [
            'name' => [
                'presence' => true,
                'length' => ['in' => [2, 128]],
            ],
            'shortname' => [
                'presence' => true,
                'uniqueness' => true,
                'length' => ['in' => [2, 128]],
                'format' => ['with' => '/\A[a-z0-9_]+\Z/', 'message' => 'must contain only lowercase letters, numbers, and underscores'],
            ],
        ];
    }

    public function normalize_fields()
    {
        $this->name = trim((string) $this->name);
        $shortname = trim((string) $this->shortname);

        if ($shortname === '' && $this->name !== '') {
            $shortname = $this->name;
        }

        $shortname = strtolower($shortname);
        $shortname = preg_replace('/\s+/', '_', $shortname);
        $shortname = preg_replace('/[^a-z0-9_]/', '', $shortname);
        $shortname = preg_replace('/_{2,}/', '_', $shortname);
        $this->shortname = trim($shortname, '_');
    }

    public function is_owner(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return (int) $this->creator_id === (int) $user->id;
    }

    public function is_maintainer(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return PostSetMaintainer::where(
            'post_set_id = ? AND user_id = ? AND status = ?',
            $this->id,
            $user->id,
            self::MAINTAINER_STATUS_APPROVED,
        )->exists();
    }

    public function has_pending_invite(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return PostSetMaintainer::where(
            'post_set_id = ? AND user_id = ? AND status = ?',
            $this->id,
            $user->id,
            self::MAINTAINER_STATUS_PENDING,
        )->exists();
    }

    public function has_blocked_invite(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return PostSetMaintainer::where(
            'post_set_id = ? AND user_id = ? AND status = ?',
            $this->id,
            $user->id,
            self::MAINTAINER_STATUS_BLOCKED,
        )->exists();
    }

    public function can_edit_settings_by(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return $this->is_owner($user) || $user->is_mod_or_higher();
    }

    public function can_edit_posts_by(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        if ($this->can_edit_settings_by($user)) {
            return true;
        }

        return $this->is_maintainer($user);
    }

    public function can_be_seen_by(User $user = null)
    {
        if ($this->is_public) {
            return true;
        }

        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return $this->can_edit_posts_by($user);
    }

    public function approved_maintainers()
    {
        return PostSetMaintainer::where('post_set_id = ? AND status = ?', $this->id, self::MAINTAINER_STATUS_APPROVED)
            ->order('updated_at DESC')
            ->take();
    }

    public function pending_maintainers()
    {
        return PostSetMaintainer::where('post_set_id = ? AND status = ?', $this->id, self::MAINTAINER_STATUS_PENDING)
            ->order('updated_at DESC')
            ->take();
    }

    public function post_ids()
    {
        $ids = self::connection()->selectValues(
            'SELECT post_id FROM post_set_posts WHERE post_set_id = ? ORDER BY id ASC',
            $this->id,
        );

        return array_map('intval', $ids ?: []);
    }

    public function update_post_count()
    {
        $count = (int) self::connection()->selectValue(
            'SELECT COUNT(*) FROM post_set_posts WHERE post_set_id = ?',
            $this->id,
        );
        $this->post_count = $count;
        self::connection()->executeSql(
            'UPDATE post_sets SET post_count = ?, updated_at = ? WHERE id = ?',
            $count,
            date('Y-m-d H:i:s'),
            $this->id,
        );
    }

    public function add_posts(array $post_ids)
    {
        $normalized_ids = self::normalize_post_ids($post_ids);
        if (empty($normalized_ids)) {
            return ['added' => [], 'invalid' => []];
        }

        $existing_post_ids = Post::where('id IN (?)', $normalized_ids)->pluck('id');
        $existing_post_ids = array_map('intval', $existing_post_ids ?: []);
        $invalid_post_ids = array_values(array_diff($normalized_ids, $existing_post_ids));
        if (empty($existing_post_ids)) {
            return ['added' => [], 'invalid' => $invalid_post_ids];
        }

        $existing_membership = self::connection()->selectValues(
            'SELECT post_id FROM post_set_posts WHERE post_set_id = ? AND post_id IN (?)',
            $this->id,
            $existing_post_ids,
        );
        $existing_membership = array_map('intval', $existing_membership ?: []);

        $to_add = array_values(array_diff($existing_post_ids, $existing_membership));
        foreach ($to_add as $post_id) {
            PostSetPost::create([
                'post_set_id' => $this->id,
                'post_id' => $post_id,
            ]);
        }

        if (!empty($to_add)) {
            $this->update_post_count();
        }

        return ['added' => $to_add, 'invalid' => $invalid_post_ids];
    }

    public function remove_posts(array $post_ids)
    {
        $normalized_ids = self::normalize_post_ids($post_ids);
        if (empty($normalized_ids)) {
            return ['removed' => []];
        }

        $existing_membership = self::connection()->selectValues(
            'SELECT post_id FROM post_set_posts WHERE post_set_id = ? AND post_id IN (?)',
            $this->id,
            $normalized_ids,
        );
        $existing_membership = array_map('intval', $existing_membership ?: []);
        if (empty($existing_membership)) {
            return ['removed' => []];
        }

        self::connection()->executeSql(
            'DELETE FROM post_set_posts WHERE post_set_id = ? AND post_id IN (?)',
            $this->id,
            $existing_membership,
        );

        $this->update_post_count();

        return ['removed' => $existing_membership];
    }

    public function sync_posts(array $post_ids)
    {
        $desired_ids = self::normalize_post_ids($post_ids);
        $existing_post_ids = Post::where('id IN (?)', $desired_ids ?: [0])->pluck('id');
        $existing_post_ids = array_map('intval', $existing_post_ids ?: []);
        $invalid_post_ids = array_values(array_diff($desired_ids, $existing_post_ids));

        $current_ids = $this->post_ids();
        $to_remove = array_values(array_diff($current_ids, $existing_post_ids));
        $to_add = array_values(array_diff($existing_post_ids, $current_ids));

        if (!empty($to_remove)) {
            self::connection()->executeSql(
                'DELETE FROM post_set_posts WHERE post_set_id = ? AND post_id IN (?)',
                $this->id,
                $to_remove,
            );
        }

        foreach ($to_add as $post_id) {
            PostSetPost::create([
                'post_set_id' => $this->id,
                'post_id' => $post_id,
            ]);
        }

        if (!empty($to_remove) || !empty($to_add)) {
            $this->update_post_count();
        }

        return [
            'added' => $to_add,
            'removed' => $to_remove,
            'invalid' => $invalid_post_ids,
        ];
    }

    public static function search_query($params, User $user = null)
    {
        if (!is_array($params)) {
            $params = [];
        }

        $query = self::where('true');
        self::apply_visibility_scope($query, $user);

        if (!empty($params['creator_id'])) {
            $query->where('creator_id = ?', (int) $params['creator_id']);
        }

        if (!empty($params['maintainer_id'])) {
            $query->where(
                'id IN (SELECT post_set_id FROM post_set_maintainers WHERE user_id = ? AND status = ?)',
                (int) $params['maintainer_id'],
                self::MAINTAINER_STATUS_APPROVED,
            );
        }

        if (!empty($params['post_id'])) {
            $query->where(
                'id IN (SELECT post_set_id FROM post_set_posts WHERE post_id = ?)',
                (int) $params['post_id'],
            );
        }

        if (isset($params['is_public']) && $params['is_public'] !== '' && $params['is_public'] !== null) {
            $query->where('is_public = ?', self::to_bool($params['is_public']) ? 1 : 0);
        }

        if (!empty($params['name'])) {
            $name = str_replace(' ', '_', trim((string) $params['name']));
            $query->where('(name LIKE ? OR shortname LIKE ?)', '%' . $name . '%', '%' . strtolower($name) . '%');
        }

        $order = isset($params['order']) ? (string) $params['order'] : '';
        switch ($order) {
            case 'name':
                $query->order('name ASC');
                break;
            case 'created':
                $query->order('created_at DESC');
                break;
            case 'updated':
                $query->order('updated_at DESC');
                break;
            default:
                $query->order('id DESC');
                break;
        }

        return $query;
    }

    public static function parse_post_ids_string($value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return [];
        }

        return self::normalize_post_ids(preg_split('/[\s,]+/', (string) $value));
    }

    public static function post_limit()
    {
        $limit = (int) CONFIG()->post_set_post_limit;
        return $limit > 0 ? $limit : 2000;
    }

    public function api_attributes()
    {
        return [
            'id' => $this->id,
            'creator_id' => $this->creator_id,
            'name' => $this->name,
            'shortname' => $this->shortname,
            'description' => (string) $this->description,
            'is_public' => (bool) $this->is_public,
            'post_count' => (int) $this->post_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }

    public function toXml(array $options = [])
    {
        $options['root'] = 'post_set';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

    private static function normalize_post_ids(array $post_ids)
    {
        $normalized = [];
        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id > 0) {
                $normalized[] = $post_id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function apply_visibility_scope($query, User $user = null)
    {
        if ($user && !$user->is_anonymous() && $user->is_mod_or_higher()) {
            return;
        }

        if (!$user || $user->is_anonymous()) {
            $query->where('is_public = ?', true);
            return;
        }

        $query->where(
            '(is_public = ? OR creator_id = ? OR id IN (SELECT post_set_id FROM post_set_maintainers WHERE user_id = ? AND status = ?))',
            true,
            $user->id,
            $user->id,
            self::MAINTAINER_STATUS_APPROVED,
        );
    }

    private static function to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
