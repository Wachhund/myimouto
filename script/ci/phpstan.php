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
$phpstanBin = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpstan';
$phpstanConfig = $root . DIRECTORY_SEPARATOR . 'phpstan.neon';
$phpstanConfigDist = $root . DIRECTORY_SEPARATOR . 'phpstan.neon.dist';

if (!is_file($phpstanBin)) {
    fwrite(STDERR, "[analyse] skipped: vendor/bin/phpstan not found\n");
    exit(0);
}

if (!is_file($phpstanConfig) && !is_file($phpstanConfigDist)) {
    echo "[analyse] skipped: no phpstan.neon or phpstan.neon.dist found\n";
    exit(0);
}

$command = [PHP_BINARY, $phpstanBin, 'analyse', '--no-progress'];
if (is_file($phpstanConfig)) {
    $command[] = '--configuration';
    $command[] = $phpstanConfig;
} elseif (is_file($phpstanConfigDist)) {
    $command[] = '--configuration';
    $command[] = $phpstanConfigDist;
}

exit(run_command($command));

