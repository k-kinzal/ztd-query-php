<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\MySql80Container;
use Fuzz\Robustness\Target\ExecutionTarget;
use SqlFaker\MySqlProvider;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

$instance = Testcontainers::run(MySql80Container::class);
$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$rawMysqli = new mysqli($host, 'root', 'root', '', $port);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawMysqli->query("CREATE DATABASE `$db`");
$rawMysqli->select_db($db);

$rawMysqli->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))');
$rawMysqli->query("INSERT INTO users VALUES (1, 'Alice', 'alice@example.com', 'active'), (2, 'Bob', 'bob@example.com', 'pending'), (3, 'Charlie', NULL, 'active')");

$rawMysqli->query('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)');
$rawMysqli->query("INSERT INTO orders VALUES (1, 1, 100.00, '2024-01-01 00:00:00'), (2, 2, 250.50, '2024-01-02 12:30:00')");

$rawMysqli->query('CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))');
$rawMysqli->query('INSERT INTO order_items VALUES (1, 1, 2), (1, 2, 1), (2, 1, 3)');

$rawMysqli->query('CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))');
$rawMysqli->query("INSERT INTO products VALUES (1, 'Widget', 19.99, 'tools'), (2, 'Gadget', 49.99, 'electronics')");

$ztdMysqli = new ZtdMysqli($host, 'root', 'root', $db, $port, null, new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception));

$ztdMysqli->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))');
$ztdMysqli->query("INSERT INTO users VALUES (1, 'Alice', 'alice@example.com', 'active'), (2, 'Bob', 'bob@example.com', 'pending'), (3, 'Charlie', NULL, 'active')");
$ztdMysqli->query('CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)');
$ztdMysqli->query("INSERT INTO orders VALUES (1, 1, 100.00, '2024-01-01 00:00:00'), (2, 2, 250.50, '2024-01-02 12:30:00')");
$ztdMysqli->query('CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))');
$ztdMysqli->query('INSERT INTO order_items VALUES (1, 1, 2), (1, 2, 1), (2, 1, 3)');
$ztdMysqli->query('CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))');
$ztdMysqli->query("INSERT INTO products VALUES (1, 'Widget', 19.99, 'tools'), (2, 'Gadget', 49.99, 'electronics')");

$parser = new MySqlParser();
$schemaParser = new MySqlSchemaParser($parser);
$shadowStore = new ShadowStore();
$registry = new TableDefinitionRegistry();

$schemas = [
    'users' => 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))',
    'orders' => 'CREATE TABLE orders (id INT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2), created_at DATETIME)',
    'order_items' => 'CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
    'products' => 'CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100))',
];

foreach ($schemas as $tableName => $createSql) {
    $definition = $schemaParser->parse($createSql);
    if ($definition !== null) {
        $registry->register($tableName, $definition);
    }
}

$shadowStore->set('users', [
    ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
    ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'pending'],
    ['id' => '3', 'name' => 'Charlie', 'email' => null, 'status' => 'active'],
]);
$shadowStore->set('orders', [
    ['id' => '1', 'user_id' => '1', 'amount' => '100.00', 'created_at' => '2024-01-01 00:00:00'],
    ['id' => '2', 'user_id' => '2', 'amount' => '250.50', 'created_at' => '2024-01-02 12:30:00'],
]);
$shadowStore->set('order_items', [
    ['order_id' => '1', 'product_id' => '1', 'quantity' => '2'],
    ['order_id' => '1', 'product_id' => '2', 'quantity' => '1'],
    ['order_id' => '2', 'product_id' => '1', 'quantity' => '3'],
]);
$shadowStore->set('products', [
    ['id' => '1', 'name' => 'Widget', 'price' => '19.99', 'category' => 'tools'],
    ['id' => '2', 'name' => 'Gadget', 'price' => '49.99', 'category' => 'electronics'],
]);

$guard = new MySqlQueryGuard($parser);
$selectTransformer = new SelectTransformer();
$insertTransformer = new InsertTransformer($parser, $selectTransformer);
$updateTransformer = new UpdateTransformer($parser, $selectTransformer);
$deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
$replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
$transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
$mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
$rewriter = new MySqlRewriter($guard, $shadowStore, $registry, $transformer, $mutationResolver, $parser);

$faker = Factory::create();
$provider = new MySqlProvider($faker, 'mysql-8.0.44');
$target = new ExecutionTarget($faker, $provider, $rawMysqli, $ztdMysqli, $shadowStore, $rewriter, $guard);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
