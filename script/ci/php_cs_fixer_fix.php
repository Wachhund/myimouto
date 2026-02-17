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
$fixerBin = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-cs-fixer';
$config = $root . DIRECTORY_SEPARATOR . '.php-cs-fixer.dist.php';

if (!is_file($fixerBin)) {
    fwrite(STDERR, "[cs-fix] skipped: vendor/bin/php-cs-fixer not found\n");
    exit(0);
}

if (!is_file($config)) {
    echo "[cs-fix] skipped: .php-cs-fixer.dist.php not found\n";
    exit(0);
}

$command = [
    PHP_BINARY,
    $fixerBin,
    'fix',
    '--using-cache=no',
    '--config=' . $config,
    '--allow-risky=no',
    '--verbose',
];

exit(run_command($command));

