<?php

/**
 * Validates compiled asset integrity for production runtime.
 *
 * Checks:
 * - manifest exists and is readable (when digest mode is enabled)
 * - each logical asset from booru config resolves to a compiled file
 * - optional gzip companions exist when gz_compression is enabled
 * - PROJ-32: size plausibility (minification targets, non-empty output)
 * - PROJ-32: optional brotli companions
 */

require dirname(__DIR__) . '/config/boot.php';

Rails::resetConfig('production');

$assets = Rails::assets();
$assetConfig = $assets->config();

$errors = [];
$warnings = [];
$checked = [];

$required = [];

foreach ((array) CONFIG()->asset_stylesheets as $asset) {
    $required[] = $asset . '.css';
}

foreach ((array) CONFIG()->asset_javascripts as $asset) {
    $required[] = $asset . '.js';
}

$required = array_values(array_unique($required));

$digestEnabled = (bool) $assetConfig->digest;
$gzipEnabled = (bool) $assetConfig->gz_compression;

$manifest = [];
$manifestPath = $assets->manifestIndexFile();

if ($digestEnabled) {
    if (!is_file($manifestPath)) {
        $errors[] = sprintf('missing manifest: %s', $manifestPath);
    } else {
        $manifest = Rails\Yaml\Parser::readFile($manifestPath);
        if (!is_array($manifest)) {
            $errors[] = sprintf('invalid manifest format: %s', $manifestPath);
            $manifest = [];
        }
    }
}

$compileRoot = rtrim(str_replace('\\', '/', $assets->compilePath()), '/');
$prefix = '/' . ltrim(str_replace('\\', '/', $assets->prefix()), '/');
$assetsRoot = $compileRoot . $prefix;

// PROJ-32: Maximum raw sizes for minified assets (bytes)
$maxSizes = [
    'js'  => 530 * 1024,  // 530 KB per JS bundle (baseline: 959/765 KB unminified)
    'css' => 56 * 1024,   // 56 KB per CSS bundle (baseline: 75 KB unminified)
];

foreach ($required as $logicalName) {
    $compiledRelative = $logicalName;

    if ($digestEnabled) {
        if (!isset($manifest[$logicalName])) {
            $errors[] = sprintf('manifest missing logical asset: %s', $logicalName);
            continue;
        }
        $compiledRelative = $manifest[$logicalName];
    }

    $compiledRelative = ltrim(str_replace('\\', '/', (string) $compiledRelative), '/');
    $compiledPath = $assetsRoot . '/' . $compiledRelative;

    $checked[] = $compiledPath;

    if (!is_file($compiledPath)) {
        $errors[] = sprintf('compiled asset missing: %s (logical: %s)', $compiledPath, $logicalName);
        continue;
    }

    $rawSize = filesize($compiledPath);

    // PROJ-32: Non-empty output check
    if ($rawSize === 0) {
        $errors[] = sprintf('compiled asset is empty: %s (logical: %s)', $compiledPath, $logicalName);
        continue;
    }

    // PROJ-32: Size plausibility check
    $ext = pathinfo($logicalName, PATHINFO_EXTENSION);
    if (isset($maxSizes[$ext]) && $rawSize > $maxSizes[$ext]) {
        $warnings[] = sprintf(
            'size exceeds target: %s is %s (max %s)',
            $logicalName,
            formatBytes($rawSize),
            formatBytes($maxSizes[$ext]),
        );
    }

    if ($gzipEnabled) {
        if (!is_file($compiledPath . '.gz')) {
            $errors[] = sprintf('gzip asset missing: %s.gz (logical: %s)', $compiledPath, $logicalName);
        } else {
            // PROJ-32: Compression ratio check
            $gzSize = filesize($compiledPath . '.gz');
            if ($gzSize > 0 && $rawSize > 0 && ($gzSize / $rawSize) > 0.80) {
                $warnings[] = sprintf(
                    'poor gzip ratio: %s (%.0f%% of raw)',
                    $logicalName,
                    ($gzSize / $rawSize) * 100,
                );
            }
        }
    }

    // PROJ-32: Optional brotli check
    if (is_file($compiledPath . '.br')) {
        $brSize = filesize($compiledPath . '.br');
        echo sprintf(
            "[assets-verify] %s: raw=%s gz=%s br=%s",
            $logicalName,
            formatBytes($rawSize),
            $gzipEnabled && is_file($compiledPath . '.gz') ? formatBytes(filesize($compiledPath . '.gz')) : 'n/a',
            formatBytes($brSize),
        ) . PHP_EOL;
    } else {
        echo sprintf(
            "[assets-verify] %s: raw=%s gz=%s",
            $logicalName,
            formatBytes($rawSize),
            $gzipEnabled && is_file($compiledPath . '.gz') ? formatBytes(filesize($compiledPath . '.gz')) : 'n/a',
        ) . PHP_EOL;
    }
}

if ($errors) {
    echo "[assets-verify] FAIL" . PHP_EOL;
    foreach ($errors as $error) {
        echo " - ERROR: " . $error . PHP_EOL;
    }
    foreach ($warnings as $warning) {
        echo " - WARNING: " . $warning . PHP_EOL;
    }
    exit(1);
}

if ($warnings) {
    foreach ($warnings as $warning) {
        echo " - WARNING: " . $warning . PHP_EOL;
    }
}

echo "[assets-verify] OK" . PHP_EOL;
echo sprintf(
    "[assets-verify] checked=%d digest=%s gzip=%s",
    count($required),
    $digestEnabled ? 'on' : 'off',
    $gzipEnabled ? 'on' : 'off',
) . PHP_EOL;

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return sprintf('%.1f MB', $bytes / 1048576);
    }
    if ($bytes >= 1024) {
        return sprintf('%.1f KB', $bytes / 1024);
    }
    return $bytes . ' B';
}
