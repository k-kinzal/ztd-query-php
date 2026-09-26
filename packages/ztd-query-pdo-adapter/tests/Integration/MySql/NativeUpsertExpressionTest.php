<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_mysql
 * @group integration
 * @group mysql
 */
#[CoversNothing]
#[Large]
final class NativeUpsertExpressionTest extends TestCase
{
    public function testDatabaseEvaluatesSubqueryUpsertExpression(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $rawPdo->exec('CREATE TABLE prices (product_id INT PRIMARY KEY, price DECIMAL(10,2))');
            $rawPdo->exec('CREATE TABLE price_list (product_id INT PRIMARY KEY, new_price DECIMAL(10,2))');
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $rawPdo->exec('INSERT INTO prices VALUES (1, 10.00)');
            $ztdPdo->exec('INSERT INTO prices VALUES (1, 10.00)');
            $rawPdo->exec('INSERT INTO price_list VALUES (1, 15.00)');
            $ztdPdo->exec('INSERT INTO price_list VALUES (1, 15.00)');

            $sql = 'INSERT INTO prices VALUES (1, 0) ON DUPLICATE KEY UPDATE price = (SELECT new_price FROM price_list WHERE product_id = 1)';
            $rawPdo->exec($sql);
            $ztdPdo->exec($sql);

            $rawStatement = $rawPdo->query('SELECT price FROM prices');
            $ztdStatement = $ztdPdo->query('SELECT price FROM prices');
            self::assertNotFalse($rawStatement);
            self::assertNotFalse($ztdStatement);
            self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testDatabaseEvaluatesJsonUpsertExpression(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $rawPdo->exec('CREATE TABLE items (id INT PRIMARY KEY, meta JSON)');
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $seed = "INSERT INTO items VALUES (1, '{\"color\":\"red\"}')";
            $rawPdo->exec($seed);
            $ztdPdo->exec($seed);

            $sql = "INSERT INTO items VALUES (1, '{\"color\":\"purple\"}') ON DUPLICATE KEY UPDATE meta = JSON_SET(meta, '$.color', 'purple')";
            $rawPdo->exec($sql);
            $ztdPdo->exec($sql);

            $rawStatement = $rawPdo->query('SELECT CAST(meta AS CHAR) AS meta FROM items');
            $ztdStatement = $ztdPdo->query('SELECT CAST(meta AS CHAR) AS meta FROM items');
            self::assertNotFalse($rawStatement);
            self::assertNotFalse($ztdStatement);
            self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testDatabaseEvaluatesConditionalUpsertExpression(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $rawPdo->exec('CREATE TABLE products (id INT PRIMARY KEY, price DECIMAL(10,2), version INT)');
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $rawPdo->exec('INSERT INTO products VALUES (1, 50.00, 5)');
            $ztdPdo->exec('INSERT INTO products VALUES (1, 50.00, 5)');

            $sql = 'INSERT INTO products VALUES (1, 15.00, 2) ON DUPLICATE KEY UPDATE price = IF(VALUES(version) > version, VALUES(price), price)';
            $rawPdo->exec($sql);
            $ztdPdo->exec($sql);

            $rawStatement = $rawPdo->query('SELECT price, version FROM products');
            $ztdStatement = $ztdPdo->query('SELECT price, version FROM products');
            self::assertNotFalse($rawStatement);
            self::assertNotFalse($ztdStatement);
            self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
