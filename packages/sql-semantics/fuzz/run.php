<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dialect = $argv[1] ?? 'all';
$mysqlVersion = getenv('MYSQL_VERSION');
$runs = $argv[2] ?? '300';
if (!ctype_digit($runs) || (int) $runs < 1) {
    fwrite(STDERR, "The run count must be a positive integer.\n");
    exit(1);
}
$versions = ['mysql' => ['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'], 'pg' => ['17.2'], 'sqlite' => ['3.47.2']];
if ($dialect !== 'all') {
    if (!isset($versions[$dialect])) {
        fwrite(STDERR, "Unknown dialect: {$dialect}\n");
        exit(1);
    }
    $versions = [$dialect => $dialect === 'mysql' ? [$mysqlVersion !== false ? $mysqlVersion : '8.4.7'] : $versions[$dialect]];
}
foreach ($versions as $name => $releases) {
    foreach ($releases as $release) {
        if ($name === 'mysql') {
            putenv('MYSQL_VERSION=' . $release);
        }
        $status = (new Fuzz\Runner())->run($name, $release, (int) $runs);
        if ($status !== 0) {
            exit($status);
        }
    }
}
