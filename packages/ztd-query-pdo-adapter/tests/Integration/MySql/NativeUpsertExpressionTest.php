<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
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
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();

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
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();

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
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();

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
