<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$paths = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'config',
    $root . DIRECTORY_SEPARATOR . 'lib',
    $root . DIRECTORY_SEPARATOR . 'script',
    $root . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrate',
];

$files = [];
foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $files[] = $file->getPathname();
    }
}

sort($files);

$failed = 0;
foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    passthru($command, $code);
    if ($code !== 0) {
        $failed++;
    }
}

echo PHP_EOL . sprintf('[ci:lint] checked=%d failed=%d', count($files), $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
