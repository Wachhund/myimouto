<?php

class TakedownPost extends Rails\ActiveRecord\Base
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';

    protected function associations()
    {
        return [
            'belongs_to' => [
                'takedown',
                'post',
            ],
        ];
    }

    protected function validations()
    {
        return [
            'takedown_id' => ['presence' => true],
            'post_id' => ['presence' => true],
        ];
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'takedown_id' => (int) $this->takedown_id,
            'post_id' => (int) $this->post_id,
            'status' => (string) $this->status,
            'created_at' => $this->created_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }
}
