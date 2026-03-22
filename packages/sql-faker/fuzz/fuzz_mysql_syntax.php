<?php

/**
 * PHP-Fuzzer entry point for MySQL SQL syntax validation.
 *
 * Usage:
 *   MYSQL_VERSION=8.0.44 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_syntax.php fuzz/corpus/mysql/
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL version to test (default: package default grammar version)
 *                   Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   MAX_DEPTH     - Grammar expansion max depth (default: 8)
 */

declare(strict_types=1);

use Fuzz\Container\MySql56Container;
use Fuzz\Container\MySql57Container;
use Fuzz\Container\MySql80Container;
use Fuzz\Container\MySql81Container;
use Fuzz\Container\MySql82Container;
use Fuzz\Container\MySql83Container;
use Fuzz\Container\MySql84Container;
use Fuzz\Container\MySql90Container;
use Fuzz\Container\MySql91Container;
use Fuzz\Support\FuzzerRuntime;
use Fuzz\Target\MySqlSyntaxTarget;
use Testcontainers\Testcontainers;

FuzzerRuntime::suppressPhpFuzzerWarnings();
FuzzerRuntime::registerPcntlAlarmReset();

$containerMap = [
    '5.6.51' => [MySql56Container::class, 'mysql-5.6.51'],
    '5.7.44' => [MySql57Container::class, 'mysql-5.7.44'],
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44'],
    '8.1.0'  => [MySql81Container::class, 'mysql-8.1.0'],
    '8.2.0'  => [MySql82Container::class, 'mysql-8.2.0'],
    '8.3.0'  => [MySql83Container::class, 'mysql-8.3.0'],
    '8.4.7'  => [MySql84Container::class, 'mysql-8.4.7'],
    '9.0.1'  => [MySql90Container::class, 'mysql-9.0.1'],
    '9.1.0'  => [MySql91Container::class, 'mysql-9.1.0'],
];

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : defaultMySqlVersion($containerMap);
$maxDepth = FuzzerRuntime::intEnv('MAX_DEPTH', 8);

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: $mysqlVersion\n");
    fwrite(STDERR, "Supported versions: " . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion] = $containerMap[$mysqlVersion];

fwrite(STDERR, "Starting MySQL $mysqlVersion container...\n");

$instance = Testcontainers::run($containerClass);

$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$pdo = new PDO(
    "mysql:host=$host;port=$port;charset=utf8mb4",
    'root',
    'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

fwrite(STDERR, "MySQL $mysqlVersion ready on $host:$port\n");
fwrite(STDERR, "Grammar version: $grammarVersion\n");
fwrite(STDERR, "Max depth: $maxDepth\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new MySqlSyntaxTarget($pdo, $grammarVersion, $maxDepth);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));

/**
 * @param array<string, array{class-string, string}> $containerMap
 */
function defaultMySqlVersion(array $containerMap): string
{
    /** @var array{default?: mixed} $meta */
    $meta = require __DIR__ . '/../resources/ast.php';
    $defaultGrammarVersion = $meta['default'] ?? null;
    if (!is_string($defaultGrammarVersion) || $defaultGrammarVersion === '') {
        throw new InvalidArgumentException('MySQL grammar metadata must expose a non-empty default version.');
    }

    foreach ($containerMap as $mysqlVersion => [, $grammarVersion]) {
        if ($grammarVersion === $defaultGrammarVersion) {
            return $mysqlVersion;
        }
    }

    throw new InvalidArgumentException(sprintf(
        'No MySQL container mapping found for default grammar version: %s',
        $defaultGrammarVersion,
    ));
}
