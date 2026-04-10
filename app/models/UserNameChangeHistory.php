<?php

class UserNameChangeHistory extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'changed_by_user' => ['class_name' => 'User', 'foreign_key' => 'changed_by'],
            ],
        ];
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'old_name' => $this->old_name,
            'new_name' => $this->new_name,
            'changed_by' => (int) $this->changed_by,
            'request_id' => $this->request_id ? (int) $this->request_id : null,
            'created_at' => $this->created_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }
}
