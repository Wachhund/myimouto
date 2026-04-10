<?php

class PostReplacement extends Rails\ActiveRecord\Base
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DELETED = 'deleted';

    protected function associations()
    {
        return [
            'belongs_to' => [
                'post',
                'creator' => ['class_name' => 'User', 'foreign_key' => 'creator_id'],
                'reviewer' => ['class_name' => 'User', 'foreign_key' => 'reviewed_by_id'],
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'before_validation' => ['normalize_fields', 'validate_source_input', 'validate_pending_uniqueness'],
        ];
    }

    protected function validations()
    {
        return [
            'post_id' => ['presence' => true],
            'creator_id' => ['presence' => true],
            'status' => ['format' => ['with' => '/\A(pending|approved|rejected|deleted)\Z/']],
        ];
    }

    public function normalize_fields()
    {
        $this->status = trim((string) $this->status);
        if ($this->status === '') {
            $this->status = self::STATUS_PENDING;
        }

        $this->reason = trim((string) $this->reason);
        if ($this->reason === '') {
            $this->reason = null;
        }

        $this->moderation_reason = trim((string) $this->moderation_reason);
        if ($this->moderation_reason === '') {
            $this->moderation_reason = null;
        }

        $this->source_url = trim((string) $this->source_url);
        if ($this->source_url === '') {
            $this->source_url = null;
        }

        $this->replacement_file_path = trim((string) $this->replacement_file_path);
        if ($this->replacement_file_path === '') {
            $this->replacement_file_path = null;
        }

        $this->replacement_file_name = trim((string) $this->replacement_file_name);
        if ($this->replacement_file_name === '') {
            $this->replacement_file_name = null;
        }
    }

    public function validate_source_input()
    {
        $has_file = (bool) $this->replacement_file_path;
        $has_url = (bool) $this->source_url;
        $status = strtolower(trim((string) $this->status));
        if ($status === '') {
            $status = self::STATUS_PENDING;
        }

        if ($status === self::STATUS_PENDING && !$has_file && !$has_url) {
            $this->errors()->add('base', 'replacement requires either an uploaded file or a source URL');
            return false;
        }

        if ($has_url && !preg_match('/\Ahttps?:\/\/\S+\Z/i', (string) $this->source_url)) {
            $this->errors()->add('source_url', 'must be HTTP or HTTPS');
            return false;
        }

        if ($has_url && !\MyImouto\PostReplacement\StagingService::isSafeSourceUrl((string) $this->source_url)) {
            $this->errors()->add('source_url', 'is not allowed');
            return false;
        }
    }

    public function validate_pending_uniqueness()
    {
        if ((string) $this->status !== self::STATUS_PENDING) {
            return;
        }

        $post_id = (int) $this->post_id;
        if ($post_id <= 0) {
            return;
        }

        $scope = self::where('post_id = ? AND status = ?', $post_id, self::STATUS_PENDING);
        if (!$this->isNewRecord()) {
            $scope = $scope->where('id <> ?', (int) $this->id);
        }

        if ($scope->exists()) {
            $this->errors()->add('post_id', 'already has a pending replacement');
            return false;
        }
    }

    public function can_be_viewed_by(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        if ($user->is_janitor_or_higher()) {
            return true;
        }

        return (int) $this->creator_id === (int) $user->id;
    }

    public function can_be_moderated_by(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return $user->is_janitor_or_higher();
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'post_id' => (int) $this->post_id,
            'creator_id' => (int) $this->creator_id,
            'reviewed_by_id' => $this->reviewed_by_id ? (int) $this->reviewed_by_id : null,
            'status' => (string) $this->status,
            'reason' => $this->reason,
            'moderation_reason' => $this->moderation_reason,
            'source_url' => $this->source_url,
            'replacement_md5' => $this->replacement_md5,
            'reviewed_at' => $this->reviewed_at,
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
        $options['root'] = 'post_replacement';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }
}
