<?php
class ModAction extends Rails\ActiveRecord\Base
{
    const ACTION_REGISTRY = [
        'user_ban' => [
            'label' => 'User Banned',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name', 'reason', 'duration']
        ],
        'user_unban' => [
            'label' => 'User Unbanned',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name']
        ],
        'user_delete' => [
            'label' => 'User Deleted',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name']
        ],
        'post_approve' => [
            'label' => 'Post Approved',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id']
        ],
        'post_delete' => [
            'label' => 'Post Deleted',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id', 'reason']
        ],
        'post_undelete' => [
            'label' => 'Post Undeleted',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id']
        ],
        'post_flag_resolve' => [
            'label' => 'Post Flag Resolved',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id']
        ],
        'user_record_create' => [
            'label' => 'User Record Created',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name', 'is_positive', 'body']
        ],
        'user_record_delete' => [
            'label' => 'User Record Deleted',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name', 'record_id']
        ],
        'ticket_claim' => [
            'label' => 'Ticket Claimed',
            'target_type' => 'ticket',
            'target_key' => 'ticket_id',
            'params' => ['ticket_id']
        ],
        'ticket_unclaim' => [
            'label' => 'Ticket Unclaimed',
            'target_type' => 'ticket',
            'target_key' => 'ticket_id',
            'params' => ['ticket_id']
        ],
        'ticket_update' => [
            'label' => 'Ticket Updated',
            'target_type' => 'ticket',
            'target_key' => 'ticket_id',
            'params' => ['ticket_id', 'status']
        ],
        'takedown_process' => [
            'label' => 'Takedown Processed',
            'target_type' => 'takedown',
            'target_key' => 'takedown_id',
            'params' => ['takedown_id', 'status']
        ],
        'upload_whitelist_create' => [
            'label' => 'Upload Whitelist Created',
            'target_type' => 'upload_whitelist',
            'target_key' => 'pattern',
            'params' => ['pattern', 'note']
        ],
        'upload_whitelist_update' => [
            'label' => 'Upload Whitelist Updated',
            'target_type' => 'upload_whitelist',
            'target_key' => 'pattern',
            'params' => ['pattern', 'note']
        ],
        'upload_whitelist_delete' => [
            'label' => 'Upload Whitelist Deleted',
            'target_type' => 'upload_whitelist',
            'target_key' => 'pattern',
            'params' => ['pattern']
        ],
        'post_replacement_approve' => [
            'label' => 'Post Replacement Approved',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id', 'replacement_id']
        ],
        'post_replacement_reject' => [
            'label' => 'Post Replacement Rejected',
            'target_type' => 'post',
            'target_key' => 'post_id',
            'params' => ['post_id', 'replacement_id', 'reason']
        ],
        'post_set_update' => [
            'label' => 'Post Set Updated',
            'target_type' => 'post_set',
            'target_key' => 'post_set_id',
            'params' => ['post_set_id', 'post_set_name']
        ],
        'username_change_approve' => [
            'label' => 'Username Change Approved',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'old_name', 'new_name']
        ],
        'username_change_reject' => [
            'label' => 'Username Change Rejected',
            'target_type' => 'user',
            'target_key' => 'user_id',
            'params' => ['user_id', 'user_name', 'reason']
        ],
    ];

    protected function associations()
    {
        return [
            'belongs_to' => [
                'creator' => ['class_name' => 'User', 'foreign_key' => 'creator_id']
            ]
        ];
    }

    /**
     * Log a moderation action. Never throws — failures are silently logged.
     *
     * @param string $action Action key from ACTION_REGISTRY
     * @param array  $values Contextual key-value pairs
     */
    public static function log(string $action, array $values = [])
    {
        try {
            $creator_id = function_exists('current_user') && current_user() && !current_user()->is_anonymous()
                ? current_user()->id
                : null;

            if (!$creator_id) {
                return;
            }

            // Warn on unknown action types but still persist the record.
            if (!isset(self::ACTION_REGISTRY[$action])) {
                error_log(sprintf('ModAction::log: unknown action type "%s"', $action));
            } else {
                // Strip keys not in the declared params schema.
                $expected = self::ACTION_REGISTRY[$action]['params'];
                $values = array_intersect_key($values, array_flip($expected));
            }

            $record = new self();
            $record->creator_id = (int)$creator_id;
            $record->action = $action;
            $record->values = !empty($values) ? json_encode($values) : null;
            $record->created_at = date('Y-m-d H:i:s');
            $record->save();
        } catch (\Throwable $e) {
            try {
                \Rails::log()->warning(
                    sprintf('ModAction::log failed for action=%s: %s', $action, $e->getMessage())
                );
            } catch (\Throwable $inner) {
                // Last-resort: swallow to never crash the caller.
            }
        }
    }

    /**
     * Decode the JSON values column into an associative array.
     */
    public function parsed_values()
    {
        if ($this->values === null || $this->values === '') {
            return [];
        }
        $decoded = json_decode((string)$this->values, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Human-readable label from the registry, or the raw action string.
     */
    public function action_label()
    {
        if (isset(self::ACTION_REGISTRY[$this->action])) {
            return self::ACTION_REGISTRY[$this->action]['label'];
        }
        return (string)$this->action;
    }

    /**
     * Target type from the registry (e.g. 'user', 'post').
     */
    public function target_type()
    {
        if (isset(self::ACTION_REGISTRY[$this->action])) {
            return self::ACTION_REGISTRY[$this->action]['target_type'];
        }
        return null;
    }

    /**
     * Target ID extracted from parsed values using the registry's target_key.
     */
    public function target_id()
    {
        $registry = self::ACTION_REGISTRY[$this->action] ?? null;
        if (!$registry) {
            return null;
        }
        $values = $this->parsed_values();
        $key = $registry['target_key'];
        return $values[$key] ?? null;
    }

    /**
     * Build a path array suitable for linkTo() based on target type and ID.
     * Returns null if no meaningful link can be constructed.
     */
    public function target_link_path()
    {
        $type = $this->target_type();
        $id = $this->target_id();

        if (!$type || $id === null) {
            return null;
        }

        switch ($type) {
            case 'user':
                return ['controller' => 'user', 'action' => 'show', 'id' => (int)$id];
            case 'post':
                return ['controller' => 'post', 'action' => 'show', 'id' => (int)$id];
            case 'post_set':
                return ['controller' => 'post_set', 'action' => 'show', 'id' => (int)$id];
            default:
                return null;
        }
    }

    /**
     * API-friendly attribute hash.
     */
    public function api_attributes()
    {
        return [
            'id' => (int)$this->id,
            'creator_id' => (int)$this->creator_id,
            'action' => (string)$this->action,
            'values' => $this->parsed_values(),
            'created_at' => $this->created_at,
        ];
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }
}
