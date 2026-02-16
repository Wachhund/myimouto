<?php
/**
 * Validates compiled asset integrity for production runtime.
 *
 * Checks:
 * - manifest exists and is readable (when digest mode is enabled)
 * - each logical asset from booru config resolves to a compiled file
 * - optional gzip companions exist when gz_compression is enabled
 */

require dirname(__DIR__) . '/config/boot.php';

Rails::resetConfig('production');

$assets = Rails::assets();
$assetConfig = $assets->config();

$errors = [];
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

    if ($gzipEnabled && !is_file($compiledPath . '.gz')) {
        $errors[] = sprintf('gzip asset missing: %s.gz (logical: %s)', $compiledPath, $logicalName);
    }
}

if ($errors) {
    echo "[assets-verify] FAIL" . PHP_EOL;
    foreach ($errors as $error) {
        echo " - " . $error . PHP_EOL;
    }
    exit(1);
}

echo "[assets-verify] OK" . PHP_EOL;
echo sprintf(
    "[assets-verify] checked=%d digest=%s gzip=%s",
    count($required),
    $digestEnabled ? 'on' : 'off',
    $gzipEnabled ? 'on' : 'off'
) . PHP_EOL;
