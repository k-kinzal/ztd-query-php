<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Integration tests for SELECT with CROSS JOIN.
 */
final class SelectCrossJoinTest extends MySqlIntegrationTestCase
{
    public function testSelectCrossJoinReturnsCartesianProduct(): void
    {
        $users = $this->uniqueTableName('users');
        $products = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, product VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$products}` VALUES (1, 'Widget'), (2, 'Gadget')");

        $rawRows = $this->rawQuery("SELECT u.name, p.product FROM `{$users}` u CROSS JOIN `{$products}` p ORDER BY u.id, p.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, p.product FROM `{$users}` u CROSS JOIN `{$products}` p ORDER BY u.id, p.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(4, $ztdRows); // 2 users x 2 products = 4
    }

    public function testSelectCrossJoinWithEmptyTableReturnsEmpty(): void
    {
        $users = $this->uniqueTableName('users');
        $products = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, product VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice')");
        // products is empty

        $rawRows = $this->rawQuery("SELECT u.name, p.product FROM `{$users}` u CROSS JOIN `{$products}` p");
        $ztdRows = $this->ztdQuery("SELECT u.name, p.product FROM `{$users}` u CROSS JOIN `{$products}` p");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(0, $ztdRows);
    }

    public function testSelectImplicitCrossJoinReturnsCartesianProduct(): void
    {
        $users = $this->uniqueTableName('users');
        $products = $this->uniqueTableName('products');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, product VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$products}` VALUES (1, 'Widget')");

        // Implicit CROSS JOIN using comma syntax
        $rawRows = $this->rawQuery("SELECT u.name, p.product FROM `{$users}` u, `{$products}` p ORDER BY u.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, p.product FROM `{$users}` u, `{$products}` p ORDER BY u.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(2, $ztdRows);
    }

    public function testSelectCrossJoinWithThreeTablesReturnsCartesianProduct(): void
    {
        $users = $this->uniqueTableName('users');
        $products = $this->uniqueTableName('products');
        $colors = $this->uniqueTableName('colors');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, product VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$colors}` (id INT PRIMARY KEY, color VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
        $this->rawPdo->exec("INSERT INTO `{$products}` VALUES (1, 'Widget')");
        $this->rawPdo->exec("INSERT INTO `{$colors}` VALUES (1, 'Red'), (2, 'Blue')");

        $rawRows = $this->rawQuery("SELECT u.name, p.product, c.color FROM `{$users}` u CROSS JOIN `{$products}` p CROSS JOIN `{$colors}` c ORDER BY u.id, c.id");
        $ztdRows = $this->ztdQuery("SELECT u.name, p.product, c.color FROM `{$users}` u CROSS JOIN `{$products}` p CROSS JOIN `{$colors}` c ORDER BY u.id, c.id");

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(4, $ztdRows); // 2 users x 1 product x 2 colors = 4
    }
}
