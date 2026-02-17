<?php
namespace MyImouto\PostReplacement;

class NotificationService
{
    const MAX_STAFF_RECIPIENTS = 25;

    public static function emitCreated(\PostReplacement $replacement)
    {
        self::logEvent('created', $replacement, (int)$replacement->creator_id);

        $staff = self::staffRecipients();
        if (!$staff) {
            return;
        }

        $from_id = (int)$replacement->creator_id;
        if ($from_id <= 0) {
            return;
        }

        $title = sprintf('Post replacement pending (#%d)', (int)$replacement->id);
        $body_lines = [
            sprintf('Post #%d has a new replacement request.', (int)$replacement->post_id),
            sprintf('Request #%d by %s.', (int)$replacement->id, \User::find_name((int)$replacement->creator_id))
        ];

        if (!empty($replacement->reason)) {
            $body_lines[] = 'Reason: ' . $replacement->reason;
        }
        if (!empty($replacement->source_url)) {
            $body_lines[] = 'Source: ' . $replacement->source_url;
        }

        $body = implode("\n", $body_lines);
        foreach ($staff as $recipient) {
            if ((int)$recipient->id === $from_id) {
                continue;
            }

            self::safeDmailCreate([
                'from_id' => $from_id,
                'to_id' => (int)$recipient->id,
                'title' => $title,
                'body' => $body
            ]);
        }
    }

    public static function emitModerationOutcome(\PostReplacement $replacement)
    {
        self::logEvent('moderated', $replacement, (int)$replacement->reviewed_by_id);

        $to_id = (int)$replacement->creator_id;
        if ($to_id <= 0) {
            return;
        }

        $from_id = (int)$replacement->reviewed_by_id;
        if ($from_id <= 0) {
            $from_id = self::fallbackSenderId($to_id);
        }
        if ($from_id <= 0) {
            return;
        }

        $title = sprintf(
            'Post replacement #%d %s',
            (int)$replacement->id,
            strtoupper((string)$replacement->status)
        );

        $body_lines = [
            sprintf('Your replacement request for post #%d is now %s.', (int)$replacement->post_id, (string)$replacement->status)
        ];

        if (!empty($replacement->moderation_reason)) {
            $body_lines[] = 'Moderator note: ' . $replacement->moderation_reason;
        }

        self::safeDmailCreate([
            'from_id' => $from_id,
            'to_id' => $to_id,
            'title' => $title,
            'body' => implode("\n", $body_lines)
        ]);
    }

    private static function logEvent($event, \PostReplacement $replacement, $actor_id)
    {
        try {
            \Rails::log()->info(sprintf(
                '[post_replacement:%s] replacement_id=%d post_id=%d actor_id=%d creator_id=%d status=%s',
                (string)$event,
                (int)$replacement->id,
                (int)$replacement->post_id,
                (int)$actor_id,
                (int)$replacement->creator_id,
                (string)$replacement->status
            ));
        } catch (\Exception $e) {
            // Logging must not break request flow.
        }
    }

    private static function staffRecipients()
    {
        $min_level = \CONFIG()->user_levels['Janitor'];
        return \User::where('level >= ?', (int)$min_level)
            ->order('id ASC')
            ->limit(self::MAX_STAFF_RECIPIENTS)
            ->take();
    }

    private static function fallbackSenderId($exclude_user_id = 0)
    {
        $admin = \User::where('level >= ? AND id <> ?', (int)\CONFIG()->user_levels['Admin'], (int)$exclude_user_id)
            ->order('id ASC')
            ->first();
        if ($admin) {
            return (int)$admin->id;
        }

        $janitor = \User::where('level >= ? AND id <> ?', (int)\CONFIG()->user_levels['Janitor'], (int)$exclude_user_id)
            ->order('id ASC')
            ->first();
        if ($janitor) {
            return (int)$janitor->id;
        }

        return 0;
    }

    private static function safeDmailCreate(array $attrs)
    {
        try {
            \Dmail::create($attrs);
        } catch (\Exception $e) {
            try {
                \Rails::log()->warning('[post_replacement] failed to send dmail: ' . $e->getMessage());
            } catch (\Exception $ignored) {
                // Ignore nested logger failures.
            }
        }
    }
}
