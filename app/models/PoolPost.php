<?php

class PoolPost extends Rails\ActiveRecord\Base
{
    use Moebooru\Versioning\VersioningTrait;

    public static function tableName()
    {
        return 'pools_posts';
    }

    public static function init_versioning($v)
    {
        $v->versioned_attributes([
            'active' => ['default' => false, 'allow_reverting_to_default' => true],
            'sequence',
        ])
            ->versioning_group_by(['class' => 'pool'])
            ->versioned_parent('pool');
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'post',
                'pool', // => ['touch' => true],
                'next_post' => ['class_name' => "Post", 'foreign_key' => "next_post_id"],
                'prev_post' => ['class_name' => "Post", 'foreign_key' => "prev_post_id"],

            ],
        ];
    }

    /**
     * MI: Force setting "active" as changed attribute on create
     * to trigger Versioning on this attribute.
     */
    protected function set_active_changed()
    {
        $this->setChangedAttribute('active', 0);
    }

    protected function callbacks()
    {
        return [
            'before_create' => ['set_active_changed'], # MI
            'after_save' => ['expire_cache'],
        ];
    }

    public function can_change(User $user, $attribute)
    {
        return $user->is_member_or_higher() || $this->pool->is_public || $user->has_permission($this->pool);
    }

    public function can_change_is_public($user)
    {
        return $user->has_permission($pool); # only the owner can change is_public
    }

    # This matches Pool.post_pretty_sequence in pool.js.
    public function pretty_sequence()
    {
        if (preg_match('/^\d+/', $this->sequence)) {
            return "#" . $this->sequence;
        } else {
            return '"' . $this->sequence . '"';
        }
    }

    # Changing pool orderings affects pool sorting in the index.
    public function expire_cache()
    {
        Moebooru\CacheHelper::expire();
    }

    public function api_attributes()
    {
        return [
            'id' => $this->id,
            'pool_id' => $this->pool_id,
            'post_id' => $this->post_id,
            'active' => $this->active,
            'sequence' => $this->sequence,
            'next_post_id' => $this->next_post_id,
            'prev_post_id' => $this->prev_post_id,
        ];
    }

    public function asJson()
    {
        return $this->api_attributes();
    }

    /**
     * Overriding Versioning's delete_history because it's a special case with
     * pools posts.
     */
    protected function delete_history()
    {
        $ids = self::connection()->selectValues("SELECT DISTINCT history_id FROM history_changes WHERE table_name = 'pools_posts' AND remote_id = ?", $this->id);
        Rails::log('IDS: ', $ids);
        $sql = "DELETE FROM histories WHERE id IN (?)";
        self::connection()->executeSql($sql, $ids);
    }
}
