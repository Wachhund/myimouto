<?php

declare(strict_types=1);

function run_command(array $parts): int
{
    $escaped = array_map('escapeshellarg', $parts);
    $command = implode(' ', $escaped);
    passthru($command, $code);
    return (int)$code;
}

$root = dirname(__DIR__, 2);
$phpunitBin = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
$phpunitConfig = $root . DIRECTORY_SEPARATOR . 'phpunit.xml';
$phpunitConfigDist = $root . DIRECTORY_SEPARATOR . 'phpunit.xml.dist';
$testsDir = $root . DIRECTORY_SEPARATOR . 'tests';
$extraArgs = array_values(array_slice($argv ?? [], 1));

if (!is_file($phpunitBin)) {
    fwrite(STDERR, "[test] skipped: vendor/bin/phpunit not found\n");
    exit(0);
}

$hasTests = false;
if (is_dir($testsDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (str_ends_with($name, 'Test.php') || str_ends_with($name, '.phpt')) {
            $hasTests = true;
            break;
        }
    }
}

$hasConfig = is_file($phpunitConfig) || is_file($phpunitConfigDist);
if (!$hasTests && !$hasConfig) {
    echo "[test] skipped: no tests directory and no phpunit config found\n";
    exit(0);
}

$command = [PHP_BINARY, $phpunitBin];
if (is_file($phpunitConfig)) {
    $command[] = '--configuration';
    $command[] = $phpunitConfig;
} elseif (is_file($phpunitConfigDist)) {
    $command[] = '--configuration';
    $command[] = $phpunitConfigDist;
}

$command[] = '--do-not-fail-on-empty-test-suite';
$command = array_merge($command, $extraArgs);
if (!$hasConfig && is_dir($testsDir)) {
    $command[] = $testsDir;
}

exit(run_command($command));

