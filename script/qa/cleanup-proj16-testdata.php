<?php

/**
 * Cleanup helper for PROJ-16 QA seed data on shared environments.
 *
 * Usage:
 *   RAILS_ENV=production php script/qa/cleanup-proj16-testdata.php --dry-run
 *   PROJ16_CLEANUP_TOKEN=your-secret RAILS_ENV=production php script/qa/cleanup-proj16-testdata.php --token=your-secret
 */

require dirname(__DIR__, 2) . '/config/boot.php';

$dryRun = in_array('--dry-run', $argv, true);
$runtimeToken = null;
$dmailCondition = '(from_id IN (?) OR to_id IN (?)) AND title LIKE ?';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--token=')) {
        $runtimeToken = substr($arg, 8);
    }
}

if (!$dryRun) {
    $requiredToken = getenv('PROJ16_CLEANUP_TOKEN');

    if ($requiredToken === false || $requiredToken === '' || $runtimeToken === null || !hash_equals($requiredToken, $runtimeToken)) {
        fwrite(STDERR, "Refusing destructive cleanup: set PROJ16_CLEANUP_TOKEN and pass matching --token=<value>." . PHP_EOL);
        exit(2);
    }
}

$targetNames = ['qa_proj16_sender', 'qa_proj16_recipient'];

$users = User::where('name IN (?, ?)', $targetNames[0], $targetNames[1])->take();
$userIds = [];

foreach ($users as $user) {
    $userIds[] = (int) $user->id;
}

if (empty($userIds)) {
    echo "No PROJ-16 test users found (qa_proj16_sender/qa_proj16_recipient)." . PHP_EOL;
    exit(0);
}

$summary = [
    'user_ids' => $userIds,
    'user_count' => User::where('id IN (?)', $userIds)->count(),
    'dmail_count' => Dmail::where(
        $dmailCondition,
        $userIds,
        $userIds,
        'PROJ16 QA%',
    )->count(),
];

echo "PROJ-16 cleanup summary:" . PHP_EOL;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($dryRun) {
    echo "Dry run only, no data was deleted." . PHP_EOL;
    exit(0);
}

try {
    User::transaction(function () use ($userIds, $dmailCondition) {
        // Remove QA dmails first to avoid orphaned rows.
        Dmail::destroyAll($dmailCondition, $userIds, $userIds, 'PROJ16 QA%');

        // Remove user-related rows if present in this environment.
        foreach (['UserBlacklistedTag', 'UserLog', 'PostVote', 'Favorite', 'Ban'] as $optionalModelClass) {
            if (class_exists($optionalModelClass)) {
                $optionalModelClass::destroyAll('user_id IN (?)', $userIds);
            }
        }

        User::destroyAll('id IN (?)', $userIds);
    });
} catch (Throwable $e) {
    fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Cleanup completed." . PHP_EOL;
