<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\TestCase;

final class UpdateTransformerTest extends TestCase
{
    public function testBuildUpdateSelectUsesAliasAndColumns(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE users u SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name']);

        $this->assertStringContainsString("SELECT 'Bob' AS `name`", $result['sql']);
        $this->assertStringContainsString("`u`.`id`", $result['sql']);
        $this->assertStringContainsString("FROM `users` AS u", $result['sql']);
        $this->assertStringContainsString("WHERE id = 1", $result['sql']);
        $this->assertSame('users', $result['table']);
        $this->assertCount(1, $result['tables']);
    }

    public function testBuildUpdateSelectWithQualifiedColumnName(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE products SET `products`.`name` = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name', 'price']);

        // Should produce single backticks, not double: AS `name` not AS ``name``
        $this->assertStringContainsString("AS `name`", $result['sql']);
        $this->assertStringNotContainsString("``name``", $result['sql']);
        $this->assertStringContainsString("`products`.`id`", $result['sql']);
        $this->assertStringContainsString("`products`.`price`", $result['sql']);
    }

    public function testBuildUpdateSelectWithUnqualifiedColumnName(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE products SET name = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name', 'price']);

        $this->assertStringContainsString("AS `name`", $result['sql']);
        $this->assertStringNotContainsString("``", $result['sql']);
        $this->assertStringContainsString("`products`.`id`", $result['sql']);
        $this->assertStringContainsString("`products`.`price`", $result['sql']);
    }

    public function testBuildUpdateSelectWithBacktickedUnqualifiedColumn(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE products SET `name` = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name']);

        // Should strip existing backticks and add single ones
        $this->assertStringContainsString("AS `name`", $result['sql']);
        $this->assertStringNotContainsString("``name``", $result['sql']);
    }

    public function testBuildUpdateSelectWithJoin(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'Updated' WHERE o.amount > 100";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name']);

        $this->assertStringContainsString("SELECT 'Updated' AS `name`", $result['sql']);
        $this->assertStringContainsString("`u`.`id`", $result['sql']);
        $this->assertStringContainsString("FROM `users` AS u", $result['sql']);
        $this->assertStringContainsString("JOIN `orders` AS o", $result['sql']);
        $this->assertStringContainsString("ON u.id = o.user_id", $result['sql']);
        $this->assertStringContainsString("WHERE o.amount > 100", $result['sql']);
    }

    public function testBuildUpdateSelectWithLeftJoin(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE users u LEFT JOIN orders o ON u.id = o.user_id SET u.status = 'inactive' WHERE o.id IS NULL";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'status']);

        $this->assertStringContainsString("SELECT 'inactive' AS `status`", $result['sql']);
        $this->assertStringContainsString("LEFT JOIN `orders` AS o", $result['sql']);
        $this->assertStringContainsString("ON u.id = o.user_id", $result['sql']);
    }

    public function testBuildUpdateSelectWithMultipleJoins(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id JOIN products p ON o.product_id = p.id SET u.name = 'VIP' WHERE p.price > 1000";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name']);

        $this->assertStringContainsString("JOIN `orders` AS o", $result['sql']);
        $this->assertStringContainsString("JOIN `products` AS p", $result['sql']);
        $this->assertStringContainsString("ON u.id = o.user_id", $result['sql']);
        $this->assertStringContainsString("ON o.product_id = p.id", $result['sql']);
    }

    public function testBuildMultiTableUpdate(): void
    {
        $transformer = new UpdateTransformer();
        $sql = "UPDATE users u, orders o SET u.name = 'Updated', o.status = 'processed' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\UpdateStatement) {
            $this->fail('Expected UpdateStatement.');
        }

        $result = $transformer->build($statement, ['id', 'name']);

        $this->assertStringContainsString("FROM `users`", $result['sql']);
        $this->assertStringContainsString("`orders`", $result['sql']);
        $this->assertSame('users', $result['table']);
        $this->assertCount(2, $result['tables']);
        $this->assertArrayHasKey('users', $result['tables']);
        $this->assertArrayHasKey('orders', $result['tables']);
    }
}
