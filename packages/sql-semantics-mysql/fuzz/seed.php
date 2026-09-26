<?php

/**
 * Copies sql-faker's byte plans without translating or filtering them.
 * Usage: php fuzz/seed.php mysql|pg|sqlite
 */

declare(strict_types=1);

$database = $argv[1] ?? '';
$mysqlVersion = getenv('MYSQL_VERSION');
$versions = [
    'mysql' => 'mysql-' . ($mysqlVersion === false ? '8.4.7' : $mysqlVersion),
    'pg' => 'pg-17.2',
    'sqlite' => 'sqlite-3.47.2',
];
if (!isset($versions[$database]) || preg_match('/\A[a-z0-9.-]+\z/D', $versions[$database]) !== 1) {
    fwrite(STDERR, "Usage: php fuzz/seed.php mysql|pg|sqlite (MYSQL_VERSION selects the MySQL release)\n");
    exit(2);
}
$destination = __DIR__ . '/corpus/' . $database;
if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
    throw new RuntimeException("Cannot create corpus directory: {$destination}");
}
$source = dirname(__DIR__) . '/vendor/k-kinzal/sql-faker/seeds/' . $database . '/' . $versions[$database];
$seeds = glob($source . '/*.txt');
if ($seeds === false) {
    throw new RuntimeException("Cannot list sql-faker seeds: {$source}");
}
foreach ($seeds as $seed) {
    if (!copy($seed, $destination . '/' . basename($seed))) {
        throw new RuntimeException("Cannot copy seed: {$seed}");
    }
}
fwrite(STDERR, count($seeds) . " sql-faker seeds copied for {$versions[$database]}\n");
