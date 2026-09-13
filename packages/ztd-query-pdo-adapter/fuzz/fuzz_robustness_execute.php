<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Robustness\ExecutionCheck;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;

[$host, $port] = Fuzz\Container\DatabaseEndpoint::mysql();
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

$rawPdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))');
$rawPdo->exec("INSERT INTO users VALUES (1, 'Alice', 'alice@example.com', 'active'), (2, 'Bob', 'bob@example.com', 'pending'), (3, 'Charlie', NULL, 'active')");

$rawPdo->exec('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)');
$rawPdo->exec("INSERT INTO orders VALUES (1, 1, 100.00, '2024-01-01 00:00:00'), (2, 2, 250.50, '2024-01-02 12:30:00')");

$rawPdo->exec('CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))');
$rawPdo->exec('INSERT INTO order_items VALUES (1, 1, 2), (1, 2, 1), (2, 1, 3)');

$rawPdo->exec('CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))');
$rawPdo->exec("INSERT INTO products VALUES (1, 'Widget', 19.99, 'tools'), (2, 'Gadget', 49.99, 'electronics')");

$version = getenv('MYSQL_VERSION') === '8.4.7' ? 'mysql-8.4.7' : 'mysql-8.0.44';
$provider = new MySqlProvider(Factory::create(), $version);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('simple_statement_or_begin')->requiringNonEmpty();
$check = new ExecutionCheck($rawPdo);

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $check->verify($provider->generate($plan), $input);
});
