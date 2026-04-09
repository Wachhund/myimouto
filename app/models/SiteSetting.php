<?php
class SiteSetting extends Rails\ActiveRecord\Base
{
    protected static function tableName()
    {
        return 'site_settings';
    }

    protected static function primaryKey()
    {
        return 'key_name';
    }

    /**
     * Get a setting value by key, with a fallback default.
     */
    public static function get(string $key, $default = null)
    {
        $record = self::where('key_name = ?', $key)->first();
        return $record ? $record->value : $default;
    }

    /**
     * Set a setting value (upsert).
     */
    public static function set(string $key, string $value)
    {
        self::connection()->executeSql(
            "INSERT INTO site_settings (key_name, value, updated_at) VALUES (?, ?, NOW()) " .
            "ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()",
            $key,
            $value
        );
    }
}
