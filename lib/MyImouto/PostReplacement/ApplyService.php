<?php
namespace MyImouto\PostReplacement;

class ApplyService
{
    public static function approve(\PostReplacement $replacement, \User $reviewer, $moderation_reason = null, array $resolved = null)
    {
        if ((string)$replacement->status !== \PostReplacement::STATUS_PENDING) {
            throw new \RuntimeException('Replacement is not in pending state');
        }

        $post = $replacement->post;
        if (!$post) {
            throw new \RuntimeException('Target post not found');
        }

        $resolved = self::resolveReplacementFile($replacement, $resolved);
        $file_path = $resolved['path'];
        $file_name = $resolved['name'];
        $from_staged_record = !empty($resolved['from_record']);
        $post_replace_context = [
            'old_paths' => [],
            'new_paths' => []
        ];

        try {
            if (!$post->replace_file_from_path($file_path, $file_name, $post_replace_context)) {
                $details = $post->errors()->fullMessages(', ');
                if (!$details) {
                    $details = 'Unable to apply replacement media';
                }
                throw new \RuntimeException($details);
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
        } catch (\Exception $e) {
            self::cleanupPostReplaceArtifacts($post_replace_context);
            if (!$from_staged_record) {
                StagingService::cleanup($file_path);
            }
            throw $e;
        }
    }

    private static function resolveReplacementFile(\PostReplacement $replacement, array $resolved = null)
    {
        if (is_array($resolved) && !empty($resolved['path'])) {
            $resolved['from_record'] = !empty($resolved['from_record']);
            $resolved['name'] = !empty($resolved['name']) ? (string)$resolved['name'] : basename((string)$resolved['path']);
            return $resolved;
        }

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

    private static function cleanupPostReplaceArtifacts(array $context)
    {
        if (empty($context['new_paths']) || !is_array($context['new_paths'])) {
            return;
        }

        $old_paths = [];
        if (!empty($context['old_paths']) && is_array($context['old_paths'])) {
            $old_paths = $context['old_paths'];
        }

        foreach ($context['new_paths'] as $path) {
            if (in_array($path, $old_paths, true)) {
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
