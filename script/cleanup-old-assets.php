<?php

/**
 * PROJ-32: Removes old digest-hashed asset files before a new build.
 *
 * Reads the current manifest.yml to identify active files, then deletes
 * any application-*.{js,css,gz,br} files that are not referenced.
 */

$publicAssets = str_replace('\\', '/', dirname(__DIR__) . '/public/assets');
$manifestFile = $publicAssets . '/manifest.yml';

$activeFiles = [];

if (is_file($manifestFile)) {
    $lines = file($manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_contains($line, ':')) {
            $value = trim(explode(':', $line, 2)[1]);
            $activeFiles[] = $value;
            $activeFiles[] = $value . '.gz';
            $activeFiles[] = $value . '.br';
        }
    }
}

$removed = 0;

$patterns = [
    $publicAssets . '/application-*',
    $publicAssets . '/moe-legacy/application-*',
];

foreach ($patterns as $pattern) {
    foreach (glob($pattern) as $file) {
        $normalizedFile = str_replace('\\', '/', $file);
        $relative = str_replace($publicAssets . '/', '', $normalizedFile);
        if (!in_array($relative, $activeFiles, true)) {
            unlink($file);
            $removed++;
            echo "[cleanup] removed: $relative" . PHP_EOL;
        }
    }
}

if ($removed === 0) {
    echo "[cleanup] no stale assets found" . PHP_EOL;
} else {
    echo "[cleanup] removed $removed stale file(s)" . PHP_EOL;
}
