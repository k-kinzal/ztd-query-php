<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteJoinTest extends MySqlIntegrationTestCase
{
    public function testDeleteWithJoin(): void
    {
        $orders = $this->uniqueTableName('orders');
        $cancelled = $this->uniqueTableName('cancelled');

        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, product VARCHAR(255), amount DECIMAL(10,2))");
        $this->rawPdo->exec("CREATE TABLE `{$cancelled}` (order_id INT PRIMARY KEY)");
        $this->ztdPdo->exec("INSERT INTO `{$orders}` VALUES (1, 'Widget', 100.00), (2, 'Gadget', 200.00), (3, 'Gizmo', 150.00)");
        $this->ztdPdo->exec("INSERT INTO `{$cancelled}` VALUES (2)");

        $affected = $this->ztdPdo->exec("DELETE o FROM `{$orders}` o INNER JOIN `{$cancelled}` c ON o.id = c.order_id");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$orders}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Widget', $ztdRows[0]['product']);
        $this->assertSame('Gizmo', $ztdRows[1]['product']);
    }

    public function testDeleteWithLeftJoin(): void
    {
        $products = $this->uniqueTableName('products');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, product_id INT)");
        $this->ztdPdo->exec("INSERT INTO `{$products}` VALUES (1, 'Widget'), (2, 'Gadget'), (3, 'Gizmo')");
        $this->ztdPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1), (2, 1)");

        $affected = $this->ztdPdo->exec("DELETE p FROM `{$products}` p LEFT JOIN `{$orders}` o ON p.id = o.product_id WHERE o.id IS NULL");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$products}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Widget', $ztdRows[0]['name']);
    }
}
