<?php
namespace MyImouto\UserDeletion;

class DeletionService
{
    /**
     * Staff-initiated deletion/anonymization.
     *
     * @param \User  $target   The user to delete/anonymize
     * @param \User  $actor    The staff member performing the action
     * @param string $reason   Reason for deletion
     * @param string $strategy Deletion strategy (currently only 'anonymize')
     * @return bool
     */
    public static function staffDelete(\User $target, \User $actor, string $reason, string $strategy = 'anonymize')
    {
        // Validate: actor must be mod_or_higher
        if (!$actor->is_mod_or_higher()) {
            throw new \RuntimeException('Insufficient permissions');
        }
        // Validate: actor level must be > target level
        if ($actor->level <= $target->level) {
            throw new \RuntimeException('Cannot delete user of equal or higher privilege');
        }
        // Validate: cannot delete self
        if ($actor->id === $target->id) {
            throw new \RuntimeException('Cannot delete yourself');
        }

        return self::executeAnonymize($target, $actor->id, 'staff', $reason);
    }

    /**
     * Self-service deletion.
     *
     * @param \User  $user     The user requesting self-deletion
     * @param string $password The user's current password for verification
     * @return bool
     */
    public static function selfDelete(\User $user, string $password)
    {
        // Validate: not staff
        if ($user->is_mod_or_higher()) {
            throw new \RuntimeException('Staff accounts cannot be self-deleted');
        }
        // Validate: account age >= 1 week
        if (strtotime($user->created_at) > strtotime('-1 week')) {
            throw new \RuntimeException('Account must be at least 1 week old');
        }
        // Validate: not banned
        if ($user->level <= \CONFIG()->user_levels['Blocked']) {
            throw new \RuntimeException('Banned accounts cannot be self-deleted');
        }
        // Validate password
        if (!\User::authenticate($user->name, $password)) {
            throw new \RuntimeException('Invalid password');
        }

        return self::executeAnonymize($user, null, 'self', 'Self-deletion requested');
    }

    /**
     * Preview the impact of deleting/anonymizing a user.
     *
     * @param \User $target The user to preview deletion for
     * @return array Associative array of table => count
     */
    public static function previewImpact(\User $target)
    {
        $conn = \Rails\ActiveRecord\Base::connection();
        $counts = [];

        $counts['post_votes'] = (int)$conn->selectValue(
            "SELECT COUNT(*) FROM post_votes WHERE user_id = ?",
            $target->id
        );
        $counts['favorites'] = (int)$conn->selectValue(
            "SELECT COUNT(*) FROM favorites WHERE user_id = ?",
            $target->id
        );
        $counts['tag_subscriptions'] = (int)$conn->selectValue(
            "SELECT COUNT(*) FROM tag_subscriptions WHERE user_id = ?",
            $target->id
        );

        return $counts;
    }

    private static function executeAnonymize(\User $target, ?int $actor_id, string $actor_type, string $reason)
    {
        $conn = \Rails\ActiveRecord\Base::connection();

        // Snapshot original values before modification
        $original_name = $target->name;
        $original_level = $target->level;

        // Preview counts for the audit record (read-only, before transaction).
        $impact = self::previewImpact($target);

        try {
            $conn->executeSql("BEGIN");

            $new_name = 'deleted_user_' . $target->id;
            // Collision guard: append suffix if name already exists
            $suffix = 0;
            while (\User::where('LOWER(name) = LOWER(?)', $new_name)->first()) {
                $suffix++;
                $new_name = 'deleted_user_' . $target->id . '_' . $suffix;
            }

            // Anonymize user record — immediate, inside transaction.
            $conn->executeSql(
                "UPDATE users SET name = ?, email = '', password_hash = '', bcrypt_password_hash = '', " .
                "remember_token = NULL, reset_token = NULL, level = ?, avatar_post_id = NULL WHERE id = ?",
                $new_name,
                \CONFIG()->user_levels['Blocked'],
                $target->id
            );

            // Create audit record with cleanup_status = 'pending'.
            // Bulk deletes (votes, favorites, subscriptions) run asynchronously
            // via the execute_user_deletion_cleanup scheduled job.
            $affected = ['users' => 1] + $impact;
            $event = new \UserDeletionEvent();
            $event->target_user_id = $target->id;
            $event->target_user_name = $original_name;
            $event->target_user_level = $original_level;
            $event->actor_id = $actor_id;
            $event->actor_type = $actor_type;
            $event->reason = $reason;
            $event->strategy = 'anonymize';
            $event->affected_records = json_encode($affected);
            $event->cleanup_status = 'pending';
            $event->created_at = date('Y-m-d H:i:s');
            $event->save();

            // Log mod action for staff-initiated deletions
            if ($actor_id) {
                \ModAction::log('user_delete', [
                    'user_id' => $target->id,
                    'user_name' => $original_name,
                    'reason' => $reason,
                    'strategy' => 'anonymize'
                ]);
            }

            $conn->executeSql("COMMIT");
            return true;
        } catch (\Exception $e) {
            $conn->executeSql("ROLLBACK");
            throw $e;
        }
    }
}
