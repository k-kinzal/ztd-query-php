<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Container\MysqliConnector;
use Fuzz\Robustness\Target\ExecutionTarget;
use PhpFuzzer\Config;
use SqlFaker\MySqlProvider;

register_shutdown_function(static function (): void {
    pcntl_alarm(0);
});

[$host, $port] = MysqliConnector::endpoint();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$native = new mysqli($host, 'root', 'root', '', $port);
$database = 'fuzz_' . bin2hex(random_bytes(8));
$native->query("CREATE DATABASE `$database`");
$native->select_db($database);
$native->set_charset('utf8mb4');
$native->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$native->query("INSERT INTO users VALUES (1, 'Alice'), (2, 'Bob')");
$version = getenv('MYSQL_VERSION');
$provider = new MySqlProvider(Factory::create(), 'mysql-' . ($version === false ? '8.0.44' : $version));
$target = new ExecutionTarget($provider, $native);

register_shutdown_function(static function () use ($native, $database): void {
    $native->query("DROP DATABASE IF EXISTS `$database`");
});

/**
 * @var Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(4096);
$config->setTarget(Closure::fromCallable($target));
