<?php
class UserOauthIdentity extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user'
            ]
        ];
    }

    protected function validations()
    {
        return [
            'user_id' => ['presence' => true],
            'provider' => ['presence' => true],
            'provider_subject' => ['presence' => true]
        ];
    }

    public function api_attributes()
    {
        return [
            'id' => (int)$this->id,
            'user_id' => (int)$this->user_id,
            'provider' => (string)$this->provider,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }

    public function toXml(array $options = [])
    {
        $options['root'] = 'user_oauth_identity';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

    protected function attrProtected()
    {
        return ['provider_subject'];
    }
}
