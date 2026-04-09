<?php
class ApiKey extends Rails\ActiveRecord\Base
{
    /**
     * Generate a new API key pair.
     *
     * @return array{raw_key: string, hashed_key: string}
     */
    public static function generate()
    {
        $raw_key = bin2hex(random_bytes(32));
        $hashed_key = hash('sha256', $raw_key);
        return ['raw_key' => $raw_key, 'hashed_key' => $hashed_key];
    }

    /**
     * Authenticate a raw API key.
     *
     * @param string $raw_key
     * @return ApiKey|null
     */
    public static function authenticate($raw_key)
    {
        if (!$raw_key || trim($raw_key) === '') {
            return null;
        }

        $hashed = hash('sha256', $raw_key);
        $key = self::where(['hashed_key' => $hashed])->first();

        if (!$key) {
            return null;
        }

        if ($key->expired()) {
            return null;
        }

        return $key;
    }

    /**
     * Check if this key has expired.
     *
     * @return bool
     */
    public function expired()
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at < date('Y-m-d H:i:s');
    }

    /**
     * Update usage tracking fields.
     *
     * @param string $ip
     * @param string $user_agent
     */
    public function touch_usage($ip, $user_agent)
    {
        $now = date('Y-m-d H:i:s');
        self::connection()->executeSql(
            "UPDATE api_keys SET last_used_at = ?, last_ip_address = ?, last_user_agent = ? WHERE id = ?",
            $now,
            substr((string)$ip, 0, 45),
            substr((string)$user_agent, 0, 255),
            $this->id
        );
        $this->last_used_at = $now;
        $this->last_ip_address = $ip;
        $this->last_user_agent = $user_agent;
    }

    /**
     * Regenerate this key. Returns the raw key (shown once).
     *
     * @return string The new raw key
     */
    public function regenerate()
    {
        $pair = self::generate();
        $now = date('Y-m-d H:i:s');
        self::connection()->executeSql(
            "UPDATE api_keys SET hashed_key = ?, created_at = ? WHERE id = ?",
            $pair['hashed_key'],
            $now,
            $this->id
        );
        $this->hashed_key = $pair['hashed_key'];
        $this->created_at = $now;
        return $pair['raw_key'];
    }

    /**
     * Maximum number of API keys allowed per user level.
     *
     * @param int $level
     * @return int
     */
    public static function max_keys_for_level($level)
    {
        if ($level >= 40) {
            return 20; // Staff (Mod+)
        }
        if ($level >= 30) {
            return 10; // Privileged+
        }
        return 5;
    }

    /**
     * API-safe attributes. Never exposes hashed_key.
     *
     * @return array
     */
    public function api_attributes()
    {
        return [
            'id' => (int)$this->id,
            'user_id' => (int)$this->user_id,
            'name' => (string)$this->name,
            'expires_at' => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'last_ip_address' => $this->last_ip_address,
            'created_at' => $this->created_at
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }

    public function toXml(array $options = [])
    {
        $options['root'] = 'api_key';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

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
            'name' => ['presence' => true],
            'user_id' => ['presence' => true]
        ];
    }

    protected function attrProtected()
    {
        return ['hashed_key', 'last_used_at', 'last_ip_address', 'last_user_agent', 'notified_at'];
    }
}
