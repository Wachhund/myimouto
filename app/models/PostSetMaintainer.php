<?php

class PostSetMaintainer extends Rails\ActiveRecord\Base
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_BLOCKED = 'blocked';

    protected function associations()
    {
        return [
            'belongs_to' => [
                'post_set' => ['class_name' => 'PostSet'],
                'user',
            ],
        ];
    }

    protected function validations()
    {
        return [
            'post_set_id' => ['presence' => true],
            'user_id' => ['presence' => true],
            'status' => ['format' => ['with' => '/\A(pending|approved|blocked)\Z/']],
        ];
    }

    public function approve()
    {
        $this->status = self::STATUS_APPROVED;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function block()
    {
        $this->status = self::STATUS_BLOCKED;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public static function find_for_set_and_user($post_set_id, $user_id)
    {
        return self::where('post_set_id = ? AND user_id = ?', (int) $post_set_id, (int) $user_id)->first();
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'post_set_id' => (int) $this->post_set_id,
            'user_id' => (int) $this->user_id,
            'status' => (string) $this->status,
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
        $options['root'] = 'post_set_maintainer';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }
}
