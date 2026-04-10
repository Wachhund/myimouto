<?php

class WikiPage extends Rails\ActiveRecord\Base
{
    use Rails\ActsAsVersioned\Versioning;

    public static function generate_sql(array $options = [])
    {
        $joins = [];
        $conds = [];
        $params = [];

        if (isset($options['title'])) {
            $conds[] = "wiki_pages.title = ?";
            $params[] = $options['title'];
        }

        if (isset($options['user_id'])) {
            $conds[] = "wiki_pages.user_id = ?";
            $params[] = $options['user_id'];
        }

        $joins = implode(" ", $joins);
        $conds = [implode(" AND ", $conds), $params];

        return [$joins, $conds];
    }

    public static function find_page($title, $version = null)
    {
        if (!$title) {
            return false;
        }

        $page = self::find_by_title($title);
        if ($version && $page) {
            $page->revertTo($version);
        }

        return $page ?: false;
    }

    public static function find_by_title($title)
    {
        return self::where("lower(title) = lower(?)", str_replace(' ', '_', $title))->first();
    }

    public function normalize_title()
    {
        $this->title = strtolower(str_replace(" ", "_", $this->title));
    }

    public function last_version()
    {
        return (int) $this->version == ($this->nextVersion() - 1);
    }

    public function first_version()
    {
        return $this->version == 1;
    }

    public function author()
    {
        return $this->user->name;
    }

    public function pretty_title()
    {
        return str_replace('_', ' ', $this->title);
    }

    public function diff($version)
    {
        if ($otherpage = self::find_page($this->title, $version)) {
            return Moebooru\Diff::generate($this->body, $otherpage->body);
        }
    }

    # FIXME: history shouldn't be changed on lock/unlock.
    #        We should instead check last post status when editing
    #        instead of doing mass update.
    public function lock()
    {
        $this->is_locked = true;

        // transaction do
        self::connection()->executeSql("UPDATE wiki_pages SET is_locked = TRUE WHERE id = ?", $this->id);
        self::connection()->executeSql("UPDATE wiki_page_versions SET is_locked = TRUE WHERE wiki_page_id = ?", $this->id);
        // end
    }

    public function unlock()
    {
        $this->is_locked = false;

        // transaction do
        self::connection()->executeSql("UPDATE wiki_pages SET is_locked = FALSE WHERE id = ?", $this->id);
        self::connection()->executeSql("UPDATE wiki_page_versions SET is_locked = FALSE WHERE wiki_page_id = ?", $this->id);
        // end
    }

    public function rename($new_title)
    {
        // if (self::exists(['wiki_pages WHERE title = ? AND id != ?', $new_title, $this->id]))
        // return false;

        self::connection()->executeSql("UPDATE wiki_pages SET title = ? WHERE id = ?", $new_title, $this->id);
        self::connection()->executeSql("UPDATE wiki_page_versions SET title = ? WHERE wiki_page_id = ?", $new_title, $this->id);

        return true;
    }

    public function toXml(array $options = [])
    {
        return parent::toXml(array_merge($options, ['root' => 'wiki_page', 'attributes' => ['id' => $this->id, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'title' => $this->title, 'body' => $this->body, 'updater_id' => $this->user_id, 'locked' => (bool) (int) $this->is_locked, 'version' => $this->version]]));
    }

    public function asJson()
    {
        return ['id' => $this->id, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'title' => $this->title, 'body' => $this->body, 'updater_id' => $this->user_id, 'locked' => $this->is_locked, 'version' => $this->version];
    }

    protected function ensure_changed()
    {
        if (!$this->titleChanged() || !$this->bodyChanged()) {
            $this->errors()->add('base', 'no_change');
        }
    }

    protected function callbacks()
    {
        return [
            'before_save' => ['normalize_title'],
            'before_validation_on_update' => ['ensure_changed'],
        ];
    }

    protected function associations()
    {
        return [
            'belongs_to' => ['user'],
        ];
    }

    protected function validations()
    {
        return [
            'title' => [
                'uniqueness' => true,
                'presence'   => true,
            ],
            'body' => [
                'presence' => true,
            ],
        ];
    }

    protected function actsAsVersionedConfig()
    {
        return [
            'table_name' => "wiki_page_versions",
            'foreign_key' => 'wiki_page_id',
        ];
    }

    protected function versioningRelation()
    {
        return self::order("updated_at DESC");
    }
}
