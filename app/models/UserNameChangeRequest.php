<?php

class UserNameChangeRequest extends Rails\ActiveRecord\Base
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'staff' => ['class_name' => 'User', 'foreign_key' => 'staff_id'],
            ],
        ];
    }

    protected function validations()
    {
        return [
            'desired_name' => [
                'presence' => true,
                'length' => ['in' => [2, 20]],
                'format' => ['with' => '/\A[^\s;,]+\Z/', 'message' => 'must not contain spaces, semicolons, or commas'],
            ],
            'validate_desired_name_available',
        ];
    }

    protected function attrProtected()
    {
        return ['status', 'staff_id', 'staff_reason', 'change_date'];
    }

    protected function callbacks()
    {
        return [
            'before_validation' => ['normalize_fields'],
        ];
    }

    public function normalize_fields()
    {
        $this->desired_name = trim((string) $this->desired_name);
        $this->reason = trim((string) $this->reason);
        if ($this->reason === '') {
            $this->reason = null;
        }
        $this->staff_reason = trim((string) $this->staff_reason);
        if ($this->staff_reason === '') {
            $this->staff_reason = null;
        }
    }

    protected function validate_desired_name_available()
    {
        if ($this->desired_name === '') {
            return;
        }

        $existing = User::where('LOWER(name) = LOWER(?)', $this->desired_name)->first();
        if ($existing && (int) $existing->id !== (int) $this->user_id) {
            $this->errors()->add('desired_name', 'is already taken');
        }
    }

    /**
     * Approve a pending username change request.
     *
     * @param User $staff_user The moderator approving the request
     * @return bool
     */
    public function approve($staff_user)
    {
        if ((string) $this->status !== self::STATUS_PENDING) {
            $this->errors()->add('status', 'request is not pending');
            return false;
        }

        $success = false;

        try {
            self::transaction(function () use ($staff_user, &$success) {
                # Re-check name availability inside transaction
                $name_taken = User::where('LOWER(name) = LOWER(?)', $this->desired_name)
                    ->where('id <> ?', (int) $this->user_id)
                    ->first();

                if ($name_taken) {
                    $this->errors()->add('desired_name', 'is already taken');
                    return;
                }

                # Update the user's name
                $user = User::find((int) $this->user_id);
                $user->updateAttribute('name', $this->desired_name);

                # Create history record
                $history = new UserNameChangeHistory();
                $history->user_id = (int) $this->user_id;
                $history->old_name = $this->old_name;
                $history->new_name = $this->desired_name;
                $history->changed_by = (int) $staff_user->id;
                $history->request_id = (int) $this->id;
                $history->created_at = date('Y-m-d H:i:s');
                $history->save();

                # Update the request
                $now = date('Y-m-d H:i:s');
                $this->status = self::STATUS_APPROVED;
                $this->staff_id = (int) $staff_user->id;
                $this->resolved_at = $now;
                $this->updated_at = $now;
                $this->save();

                # Log the moderation action
                ModAction::log('username_change_approve', [
                    'user_id' => (int) $this->user_id,
                    'old_name' => $this->old_name,
                    'new_name' => $this->desired_name,
                ]);

                $success = true;
            });
        } catch (\Exception $e) {
            $this->errors()->add('base', $e->getMessage());
            return false;
        }

        return $success;
    }

    /**
     * Reject a pending username change request.
     *
     * @param User   $staff_user The moderator rejecting the request
     * @param string $reason     Reason for rejection
     * @return bool
     */
    public function reject($staff_user, $reason = null)
    {
        if ((string) $this->status !== self::STATUS_PENDING) {
            $this->errors()->add('status', 'request is not pending');
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->status = self::STATUS_REJECTED;
        $this->staff_id = (int) $staff_user->id;
        $this->staff_reason = $reason;
        $this->resolved_at = $now;
        $this->updated_at = $now;

        $saved = $this->save();

        if ($saved) {
            ModAction::log('username_change_reject', [
                'user_id' => (int) $this->user_id,
                'user_name' => $this->old_name,
                'reason' => $reason,
            ]);
        }

        return $saved;
    }

    /**
     * Cancel a pending request (by the requesting user).
     *
     * @return bool
     */
    public function cancel()
    {
        if ((string) $this->status !== self::STATUS_PENDING) {
            $this->errors()->add('status', 'request is not pending');
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->status = self::STATUS_CANCELLED;
        $this->resolved_at = $now;
        $this->updated_at = $now;

        return $this->save();
    }

    /**
     * Check whether a user is allowed to submit a new username change request.
     *
     * @param User $user
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public static function can_request($user)
    {
        if (!$user || $user->is_anonymous()) {
            return ['allowed' => false, 'reason' => 'You must be logged in'];
        }

        # Check for existing pending request
        $pending = self::where('user_id = ? AND status = ?', $user->id, self::STATUS_PENDING)->first();
        if ($pending) {
            return ['allowed' => false, 'reason' => 'You already have a pending username change request'];
        }

        # Check cooldown period
        $cooldown_days = CONFIG()->username_change_cooldown_days ?: 90;
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $cooldown_days . ' days'));

        $recent = UserNameChangeHistory::where('user_id = ?', $user->id)
            ->where('created_at > ?', $cutoff)
            ->order('created_at DESC')
            ->first();

        if ($recent) {
            $next_allowed = date('Y-m-d', strtotime($recent->created_at . ' +' . (int) $cooldown_days . ' days'));
            return ['allowed' => false, 'reason' => 'You can request a username change again after ' . $next_allowed];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'old_name' => $this->old_name,
            'desired_name' => $this->desired_name,
            'reason' => $this->reason,
            'status' => (string) $this->status,
            'staff_id' => $this->staff_id ? (int) $this->staff_id : null,
            'staff_reason' => $this->staff_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }

    public function toXml(array $options = [])
    {
        $options['root'] = 'user_name_change_request';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }
}
