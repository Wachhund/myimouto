<?php
class UserRecord extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'reporter' => ['foreign_key' => "reported_by", 'class_name' => "User"]
            ]
        ];
    }

    protected function validations()
    {
        return [
            'user_id'     => ['presence' => true],
            'reported_by' => ['presence' => true],
            'validate_not_self_record'
        ];
    }

    protected function validate_not_self_record()
    {
        if ($this->user_id && $this->reported_by && $this->user_id == $this->reported_by) {
            $this->errors()->add('user_id', 'Cannot create a record for yourself');
        }
    }

    protected function setUser($name)
    {
        $this->user_id = ($user = User::where(['name' => $name])->first()) ? $user->id : null;
    }

    /**
     * Soft-delete this record instead of destroying it.
     */
    public function soft_delete($deleted_by_id)
    {
        return $this->updateAttributes([
            'is_deleted'  => 1,
            'deleted_by'  => $deleted_by_id,
            'updated_at'  => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Backward-compatible check for positive records.
     */
    public function is_positive()
    {
        return $this->category === 'positive';
    }

    /**
     * Return a query scoped to active (non-deleted) records.
     */
    public static function active()
    {
        return self::where('is_deleted = 0');
    }

    /**
     * API-friendly attribute hash.
     */
    public function api_attributes()
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'reported_by' => $this->reported_by,
            'category'    => $this->category,
            'body'        => $this->body,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'is_deleted'  => (bool)$this->is_deleted
        ];
    }
}
