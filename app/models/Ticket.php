<?php
class Ticket extends Rails\ActiveRecord\Base
{
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED
    ];

    const VALID_QTYPES = [
        'post', 'comment', 'dmail', 'forum', 'pool', 'set', 'user', 'wiki', 'replacement'
    ];

    const MAX_PENDING_PER_USER = 10;

    protected function associations()
    {
        return [
            'belongs_to' => [
                'creator' => ['class_name' => 'User', 'foreign_key' => 'creator_id'],
                'accused' => ['class_name' => 'User', 'foreign_key' => 'accused_id'],
                'claimant' => ['class_name' => 'User', 'foreign_key' => 'claimant_id']
            ]
        ];
    }

    protected function callbacks()
    {
        return [
            'before_validation' => ['normalize_fields']
        ];
    }

    protected function validations()
    {
        return [
            'reason' => ['presence' => true],
            'creator_id' => ['presence' => true],
            'validate_qtype',
            'validate_pending_limit'
        ];
    }

    public function normalize_fields()
    {
        $this->reason = trim((string)$this->reason);
        if ($this->reason === '') {
            $this->reason = null;
        }

        $this->response = trim((string)$this->response);
        if ($this->response === '') {
            $this->response = null;
        }

        $this->qtype = trim(strtolower((string)$this->qtype));
        if ($this->qtype === '') {
            $this->qtype = 'post';
        }

        $this->status = trim(strtolower((string)$this->status));
        if ($this->status === '') {
            $this->status = self::STATUS_PENDING;
        }

        $this->model_type = trim((string)$this->model_type);
        if ($this->model_type === '') {
            $this->model_type = null;
        }
    }

    protected function validate_qtype()
    {
        if (!in_array($this->qtype, self::VALID_QTYPES, true)) {
            $this->errors()->add('qtype', 'is not a valid ticket type');
        }
    }

    protected function validate_pending_limit()
    {
        if (!$this->isNewRecord()) {
            return;
        }

        if (!$this->creator_id) {
            return;
        }

        if (!self::can_create_ticket_by_user_id((int)$this->creator_id)) {
            $this->errors()->add('base', 'You have too many pending tickets (max ' . self::MAX_PENDING_PER_USER . ')');
        }
    }

    /**
     * Check whether a user can create another ticket (rate limit).
     */
    public static function can_create_ticket($user)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        return self::can_create_ticket_by_user_id((int)$user->id);
    }

    /**
     * Check pending ticket count for a given user ID.
     */
    public static function can_create_ticket_by_user_id($user_id)
    {
        $count = (int)self::where('creator_id = ? AND status IN (?)', $user_id, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS])->count();
        return $count < self::MAX_PENDING_PER_USER;
    }

    /**
     * Claim this ticket for a staff member.
     */
    public function claim($staff)
    {
        if ((string)$this->status !== self::STATUS_PENDING && (string)$this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $this->claimant_id = (int)$staff->id;
        $this->status = self::STATUS_IN_PROGRESS;
        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->save();

        if ($result) {
            ModAction::log('ticket_claim', ['ticket_id' => (int)$this->id]);
        }

        return $result;
    }

    /**
     * Release claim on this ticket.
     */
    public function unclaim()
    {
        if ((string)$this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $old_claimant = $this->claimant_id;
        $this->claimant_id = null;
        $this->status = self::STATUS_PENDING;
        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->save();

        if ($result) {
            ModAction::log('ticket_unclaim', ['ticket_id' => (int)$this->id]);
        }

        return $result;
    }

    /**
     * Resolve a ticket (approve or reject) with a response.
     */
    public function resolve($staff, $response, $status = self::STATUS_APPROVED)
    {
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return false;
        }

        $this->claimant_id = (int)$staff->id;
        $this->response = trim((string)$response);
        $this->status = $status;
        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->save();

        if ($result) {
            ModAction::log('ticket_update', ['ticket_id' => (int)$this->id, 'status' => $status]);
        }

        return $result;
    }

    /**
     * Update the response and optionally the status.
     */
    public function update_response($staff, $response, $status = null)
    {
        $this->claimant_id = (int)$staff->id;
        $this->response = trim((string)$response);
        $this->updated_at = date('Y-m-d H:i:s');

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $this->status = $status;
        }

        $result = $this->save();

        if ($result) {
            ModAction::log('ticket_update', ['ticket_id' => (int)$this->id, 'status' => (string)$this->status]);
        }

        return $result;
    }

    /**
     * Human-readable status label.
     */
    public function status_label()
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected'
        ];

        return $labels[(string)$this->status] ?? (string)$this->status;
    }

    /**
     * CSS class for status badge styling.
     */
    public function status_class()
    {
        $classes = [
            self::STATUS_PENDING => 'ticket-status-pending',
            self::STATUS_IN_PROGRESS => 'ticket-status-in-progress',
            self::STATUS_APPROVED => 'ticket-status-approved',
            self::STATUS_REJECTED => 'ticket-status-rejected'
        ];

        return $classes[(string)$this->status] ?? '';
    }

    /**
     * API-friendly attribute hash.
     */
    public function api_attributes()
    {
        return [
            'id' => (int)$this->id,
            'creator_id' => (int)$this->creator_id,
            'accused_id' => $this->accused_id ? (int)$this->accused_id : null,
            'qtype' => (string)$this->qtype,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id ? (int)$this->model_id : null,
            'status' => (string)$this->status,
            'claimant_id' => $this->claimant_id ? (int)$this->claimant_id : null,
            'reason' => (string)$this->reason,
            'response' => $this->response,
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
        $options['root'] = 'ticket';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }
}
