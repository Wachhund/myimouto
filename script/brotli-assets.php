<?php
/**
 * PROJ-32: Generates .br (Brotli) compressed companions for compiled assets.
 * Skips silently if the brotli CLI tool is not available.
 */

$brotli = '';
if (PHP_OS_FAMILY === 'Windows') {
    $brotli = trim((string) shell_exec('where brotli 2>NUL'));
    if ($brotli !== '') {
        $brotli = explode("\n", $brotli)[0];
        $brotli = trim($brotli);
    }
} else {
    $brotli = trim((string) shell_exec('which brotli 2>/dev/null'));
}

if ($brotli === '') {
    echo "[brotli] brotli CLI not found, skipping .br generation" . PHP_EOL;
    exit(0);
}

echo "[brotli] using: $brotli" . PHP_EOL;

$publicAssets = str_replace('\\', '/', dirname(__DIR__) . '/public/assets');
$patterns = [
    $publicAssets . '/application-*.js',
    $publicAssets . '/application-*.css',
    $publicAssets . '/application.js',
    $publicAssets . '/application.css',
    $publicAssets . '/moe-legacy/application-*.js',
    $publicAssets . '/moe-legacy/application.js',
];

$created = 0;

foreach ($patterns as $pattern) {
    foreach (glob($pattern) as $file) {
        if (str_ends_with($file, '.gz') || str_ends_with($file, '.br')) {
            continue;
        }
        $brFile = $file . '.br';
        $cmd = escapeshellarg($brotli) . ' --best --keep --force --output=' . escapeshellarg($brFile) . ' ' . escapeshellarg($file) . ' 2>&1';
        shell_exec($cmd);
        if (is_file($brFile)) {
            $created++;
        } else {
            echo "[brotli] FAILED: $file" . PHP_EOL;
        }
    }
}

echo "[brotli] created $created .br file(s)" . PHP_EOL;
