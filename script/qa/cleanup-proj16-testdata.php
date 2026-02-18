<?php

/**
 * Cleanup helper for PROJ-16 QA seed data on shared environments.
 *
 * Usage:
 *   RAILS_ENV=production php script/qa/cleanup-proj16-testdata.php --dry-run
 *   RAILS_ENV=production php script/qa/cleanup-proj16-testdata.php
 */

require dirname(__DIR__, 2) . '/config/boot.php';

$dryRun = in_array('--dry-run', $argv, true);
$targetNames = ['qa_proj16_sender', 'qa_proj16_recipient'];

$users = User::where('name IN (?, ?)', $targetNames[0], $targetNames[1])->take();
$userIds = [];

foreach ($users as $user) {
    $userIds[] = (int)$user->id;
}

if (empty($userIds)) {
    echo "No PROJ-16 test users found (qa_proj16_sender/qa_proj16_recipient)." . PHP_EOL;
    exit(0);
}

$idList = implode(',', array_map('intval', $userIds));

$summary = [
    'user_ids' => $userIds,
    'user_count' => User::where('id IN (' . $idList . ')')->count(),
    'dmail_count' => Dmail::where(
        '(from_id IN (' . $idList . ') OR to_id IN (' . $idList . ')) OR title LIKE ?',
        'PROJ16 QA%'
    )->count(),
];

echo "PROJ-16 cleanup summary:" . PHP_EOL;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($dryRun) {
    echo "Dry run only, no data was deleted." . PHP_EOL;
    exit(0);
}

// Remove QA dmails first to avoid orphaned rows.
Dmail::destroyAll(
    '(from_id IN (' . $idList . ') OR to_id IN (' . $idList . ')) OR title LIKE ?',
    'PROJ16 QA%'
);

// Remove user-related rows if present in this environment.
if (class_exists('UserBlacklistedTag', false) || class_exists('UserBlacklistedTag')) {
    UserBlacklistedTag::destroyAll('user_id IN (' . $idList . ')');
}

if (class_exists('UserLog', false) || class_exists('UserLog')) {
    UserLog::destroyAll('user_id IN (' . $idList . ')');
}

if (class_exists('PostVote', false) || class_exists('PostVote')) {
    PostVote::destroyAll('user_id IN (' . $idList . ')');
}

if (class_exists('Favorite', false) || class_exists('Favorite')) {
    Favorite::destroyAll('user_id IN (' . $idList . ')');
}

if (class_exists('Ban', false) || class_exists('Ban')) {
    Ban::destroyAll('user_id IN (' . $idList . ')');
}

User::destroyAll('id IN (' . $idList . ')');

echo "Cleanup completed." . PHP_EOL;
