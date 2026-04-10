<?php

class UserDeletionEvent extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'target_user' => ['class_name' => 'User', 'foreign_key' => 'target_user_id'],
                'actor' => ['class_name' => 'User', 'foreign_key' => 'actor_id'],
            ],
        ];
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'target_user_id' => (int) $this->target_user_id,
            'target_user_name' => (string) $this->target_user_name,
            'target_user_level' => (int) $this->target_user_level,
            'actor_id' => $this->actor_id ? (int) $this->actor_id : null,
            'actor_type' => (string) $this->actor_type,
            'reason' => (string) $this->reason,
            'strategy' => (string) $this->strategy,
            'affected_records' => $this->affected_records ? json_decode((string) $this->affected_records, true) : null,
            'cleanup_status' => (string) ($this->cleanup_status ?? 'completed'),
            'created_at' => $this->created_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }
}
