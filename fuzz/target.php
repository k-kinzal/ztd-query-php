<?php

/**
 * PHP-Fuzzer target for ZTD crash detection.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/target.php fuzz/corpus/
 */

declare(strict_types=1);

// Disable php-fuzzer's timeout handler before testcontainers shutdown
// This must be registered BEFORE Testcontainers::run() so it executes FIRST in FIFO order
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Container\MySql80Container;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\SqlParseException;
use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use Testcontainers\Testcontainers;
use Faker\Factory;
use SqlFaker\MySqlProvider;

$instance = Testcontainers::run(MySql80Container::class);
$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());
$dsn = "mysql:host=$host;port=$port;charset=utf8mb4";

$rawPdo = new PDO($dsn, 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawPdo->exec("CREATE DATABASE `$db`");

$dbDsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
$rawPdo = new PDO($dbDsn, 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$rawPdo->exec("CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    status VARCHAR(50)
)");
$rawPdo->exec("INSERT INTO users (id, name, email, status) VALUES
    (1, 'Alice', 'alice@example.com', 'active'),
    (2, 'Bob', 'bob@example.com', 'pending')
");

$ztdPdo = new ZtdPdo($dbDsn, 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
], new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception));
$ztdPdo->enableZtd();

// Set up shadow schema and data via SQL statements
$ztdPdo->exec("CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    status VARCHAR(50)
)");
$ztdPdo->exec("INSERT INTO users (id, name, email, status) VALUES
    (1, 'Alice', 'alice@example.com', 'active'),
    (2, 'Bob', 'bob@example.com', 'pending')
");

$faker = Factory::create();
$provider = new MySqlProvider($faker, 'mysql-8.0.44');

/** @var \PhpFuzzer\Config $config */
$config->setTarget(function (string $input) use ($rawPdo, $ztdPdo, $faker, $provider): void {
    $seed = crc32(str_pad($input, 4, "\0"));
    $faker->seed($seed);
    $sql = $provider->sql(maxDepth: 8);

    // Skip syntax errors
    try {
        $rawPdo->prepare($sql);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1064) {
            return;
        }
    }

    // Execute through ZTD
    try {
        $upper = strtoupper(ltrim($sql));
        if (str_starts_with($upper, 'SELECT') || str_starts_with($upper, 'WITH') || str_starts_with($upper, '(SELECT')) {
            $stmt = $ztdPdo->query($sql);
            $stmt !== false && $stmt->fetchAll();
        } else {
            $ztdPdo->exec($sql);
        }
    } catch (SchemaNotFoundException
           | ColumnNotFoundException
           | TableAlreadyExistsException
           | ColumnAlreadyExistsException
           | DuplicateKeyException
           | ForeignKeyViolationException
           | NotNullViolationException
           | UnsupportedSqlException
           | SqlParseException
           | UnknownSchemaException) {
               return;
           } catch (PDOException $e) {
               // Expected DB errors (type conversion, constraint violations)
               if (in_array($e->errorInfo[1] ?? 0, [1048, 1054, 1146, 1264, 1292, 1366, 1406], true)) {
                   return;
               }
               throw new Error("ZTD crash: seed=$seed sql=$sql error={$e->getMessage()}");
           } catch (Throwable $e) {
               throw new Error("ZTD crash: seed=$seed sql=$sql error={$e->getMessage()}");
           }
});
