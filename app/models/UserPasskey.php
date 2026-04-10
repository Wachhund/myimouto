<?php

class UserPasskey extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
            ],
        ];
    }

    protected function validations()
    {
        return [
            'user_id' => ['presence' => true],
            'credential_id' => ['presence' => true],
            'public_key' => ['presence' => true],
        ];
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'credential_id' => (string) $this->credential_id,
            'sign_count' => (int) $this->sign_count,
            'device_label' => $this->device_label,
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
        $options['root'] = 'user_passkey';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

    protected function attrProtected()
    {
        return ['public_key', 'sign_count'];
    }
}
