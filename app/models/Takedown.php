<?php
class Takedown extends Rails\ActiveRecord\Base
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_PARTIAL = 'partial';

    const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_DENIED,
        self::STATUS_PARTIAL
    ];

    protected function associations()
    {
        return [
            'belongs_to' => [
                'creator' => ['class_name' => 'User', 'foreign_key' => 'creator_id'],
                'approver' => ['class_name' => 'User', 'foreign_key' => 'approver_id']
            ],
            'has_many' => [
                'takedown_posts' => ['class_name' => 'TakedownPost', 'dependent' => 'delete_all']
            ]
        ];
    }

    protected function callbacks()
    {
        return [
            'before_validation' => ['normalize_fields'],
            'before_create' => ['generate_vericode']
        ];
    }

    protected function validations()
    {
        return [
            'reason' => ['presence' => true]
        ];
    }

    public function normalize_fields()
    {
        $this->reason = trim((string)$this->reason);
        if ($this->reason === '') {
            $this->reason = null;
        }

        $this->email = trim((string)$this->email);
        if ($this->email === '') {
            $this->email = null;
        }

        $this->source = trim((string)$this->source);
        if ($this->source === '') {
            $this->source = null;
        }

        $this->instructions = trim((string)$this->instructions);
        if ($this->instructions === '') {
            $this->instructions = null;
        }

        $this->notes = trim((string)$this->notes);
        if ($this->notes === '') {
            $this->notes = null;
        }

        $this->status = trim(strtolower((string)$this->status));
        if ($this->status === '') {
            $this->status = self::STATUS_PENDING;
        }
    }

    public function generate_vericode()
    {
        if (empty($this->vericode)) {
            $this->vericode = bin2hex(random_bytes(16));
        }
    }

    /**
     * Process this takedown: approve, deny, or partial.
     */
    public function process($staff, $status)
    {
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_PARTIAL], true)) {
            return false;
        }

        $this->approver_id = (int)$staff->id;
        $this->status = $status;
        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->save();

        if ($result) {
            ModAction::log('takedown_process', ['takedown_id' => (int)$this->id, 'status' => $status]);
        }

        return $result;
    }

    /**
     * Add posts to this takedown.
     *
     * @param array $post_ids Array of post IDs
     * @return array Result with 'added' and 'invalid' keys
     */
    public function add_posts(array $post_ids)
    {
        $normalized = self::normalize_post_ids($post_ids);
        if (empty($normalized)) {
            return ['added' => [], 'invalid' => []];
        }

        $existing_post_ids = Post::where('id IN (?)', $normalized)->pluck('id');
        $existing_post_ids = array_map('intval', $existing_post_ids ?: []);
        $invalid_post_ids = array_values(array_diff($normalized, $existing_post_ids));

        if (empty($existing_post_ids)) {
            return ['added' => [], 'invalid' => $invalid_post_ids];
        }

        $already_linked = self::connection()->selectValues(
            'SELECT post_id FROM takedown_posts WHERE takedown_id = ? AND post_id IN (?)',
            $this->id,
            $existing_post_ids
        );
        $already_linked = array_map('intval', $already_linked ?: []);

        $to_add = array_values(array_diff($existing_post_ids, $already_linked));
        foreach ($to_add as $post_id) {
            TakedownPost::create([
                'takedown_id' => $this->id,
                'post_id' => $post_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        return ['added' => $to_add, 'invalid' => $invalid_post_ids];
    }

    /**
     * Remove posts from this takedown.
     *
     * @param array $post_ids Array of post IDs
     * @return array Result with 'removed' key
     */
    public function remove_posts(array $post_ids)
    {
        $normalized = self::normalize_post_ids($post_ids);
        if (empty($normalized)) {
            return ['removed' => []];
        }

        $existing = self::connection()->selectValues(
            'SELECT post_id FROM takedown_posts WHERE takedown_id = ? AND post_id IN (?)',
            $this->id,
            $normalized
        );
        $existing = array_map('intval', $existing ?: []);

        if (empty($existing)) {
            return ['removed' => []];
        }

        self::connection()->executeSql(
            'DELETE FROM takedown_posts WHERE takedown_id = ? AND post_id IN (?)',
            $this->id,
            $existing
        );

        return ['removed' => $existing];
    }

    /**
     * Get all post IDs linked to this takedown.
     */
    public function post_ids()
    {
        $ids = self::connection()->selectValues(
            'SELECT post_id FROM takedown_posts WHERE takedown_id = ? ORDER BY id ASC',
            $this->id
        );
        return array_map('intval', $ids ?: []);
    }

    /**
     * Count of linked posts by status.
     */
    public function post_count($status = null)
    {
        if ($status !== null) {
            return (int)self::connection()->selectValue(
                'SELECT COUNT(*) FROM takedown_posts WHERE takedown_id = ? AND status = ?',
                $this->id,
                $status
            );
        }

        return (int)self::connection()->selectValue(
            'SELECT COUNT(*) FROM takedown_posts WHERE takedown_id = ?',
            $this->id
        );
    }

    /**
     * Human-readable status label.
     */
    public function status_label()
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DENIED => 'Denied',
            self::STATUS_PARTIAL => 'Partial'
        ];

        return $labels[(string)$this->status] ?? (string)$this->status;
    }

    /**
     * API-friendly attribute hash.
     */
    public function api_attributes()
    {
        return [
            'id' => (int)$this->id,
            'creator_id' => $this->creator_id ? (int)$this->creator_id : null,
            'email' => $this->email,
            'source' => $this->source,
            'reason' => (string)$this->reason,
            'status' => (string)$this->status,
            'vericode' => (string)$this->vericode,
            'instructions' => $this->instructions,
            'approver_id' => $this->approver_id ? (int)$this->approver_id : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Public-facing attributes (limited info for vericode status check).
     */
    public function public_attributes()
    {
        return [
            'id' => (int)$this->id,
            'status' => (string)$this->status,
            'instructions' => $this->instructions,
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
        $options['root'] = 'takedown';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

    private static function normalize_post_ids(array $post_ids)
    {
        $normalized = [];
        foreach ($post_ids as $post_id) {
            $post_id = (int)$post_id;
            if ($post_id > 0) {
                $normalized[] = $post_id;
            }
        }
        return array_values(array_unique($normalized));
    }
}
