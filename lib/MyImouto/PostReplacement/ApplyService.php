<?php
namespace MyImouto\PostReplacement;

class ApplyService
{
    public static function approve(\PostReplacement $replacement, \User $reviewer, $moderation_reason = null)
    {
        if ((string)$replacement->status !== \PostReplacement::STATUS_PENDING) {
            throw new \RuntimeException('Replacement is not in pending state');
        }

        $post = $replacement->post;
        if (!$post) {
            throw new \RuntimeException('Target post not found');
        }

        $resolved = self::resolveReplacementFile($replacement);
        $file_path = $resolved['path'];
        $file_name = $resolved['name'];
        $from_staged_record = !empty($resolved['from_record']);

        try {
            if (!$post->replace_file_from_path($file_path, $file_name)) {
                $details = $post->errors()->fullMessages(', ');
                if (!$details) {
                    $details = 'Unable to apply replacement media';
                }
                throw new \RuntimeException($details);
            }
        } catch (\Exception $e) {
            if (!$from_staged_record) {
                StagingService::cleanup($file_path);
            }
            throw $e;
        }

        $replacement->status = \PostReplacement::STATUS_APPROVED;
        $replacement->reviewed_by_id = (int)$reviewer->id;
        $replacement->reviewed_at = date('Y-m-d H:i:s');
        $replacement->moderation_reason = self::normalizeOptionalText($moderation_reason);
        $replacement->replacement_md5 = (string)$post->md5;

        // Uploaded replacement payload is no longer needed after approval.
        $replacement->replacement_file_path = null;
        $replacement->replacement_file_name = null;

        if (!$replacement->save()) {
            throw new \RuntimeException($replacement->errors()->fullMessages(', '));
        }

        StagingService::cleanup($file_path);
        NotificationService::emitModerationOutcome($replacement);

        return $replacement;
    }

    private static function resolveReplacementFile(\PostReplacement $replacement)
    {
        if (!empty($replacement->replacement_file_path) && is_file($replacement->replacement_file_path)) {
            return [
                'path' => $replacement->replacement_file_path,
                'name' => $replacement->replacement_file_name ?: basename($replacement->replacement_file_path),
                'from_record' => true
            ];
        }

        if (!empty($replacement->source_url)) {
            $staged = StagingService::downloadFromSource($replacement->source_url);
            return [
                'path' => $staged['path'],
                'name' => $staged['name'],
                'from_record' => false
            ];
        }

        throw new \RuntimeException('No replacement payload available');
    }

    private static function normalizeOptionalText($text)
    {
        $text = trim((string)$text);
        return $text === '' ? null : $text;
    }
}
