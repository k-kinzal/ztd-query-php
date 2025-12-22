<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\TestCase;

final class DeleteTransformerTest extends TestCase
{
    public function testBuildDeleteWithJoinAlias(): void
    {
        $transformer = new DeleteTransformer();
        $sql = "DELETE u FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $result = $transformer->build($statement, $sql, ['id', 'name']);

        $this->assertSame('users', $result['table']);
        $this->assertStringContainsString('SELECT `u`.`id` AS `id`, `u`.`name` AS `name`', $result['sql']);
        $this->assertStringContainsString('FROM users', $result['sql']);
        $this->assertStringContainsString('AS `u`', $result['sql']);
        $this->assertStringContainsString('JOIN orders', $result['sql']);
        $this->assertStringContainsString('AS `o`', $result['sql']);
        $this->assertStringContainsString("WHERE o.status = 'canceled'", $result['sql']);
    }

    public function testBuildDeleteWithPartitionIsBlocked(): void
    {
        $transformer = new DeleteTransformer();
        $sql = "DELETE FROM users PARTITION (p0) WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PARTITION clause');

        $transformer->build($statement, $sql, ['id']);
    }

    public function testBuildDeleteWithMultipleTargetsReturnsAllTables(): void
    {
        $transformer = new DeleteTransformer();
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $result = $transformer->build($statement, $sql, ['id']);

        // Primary table is the first target
        $this->assertSame('users', $result['table']);
        // Tables array should contain both target tables
        $this->assertArrayHasKey('tables', $result);
        $this->assertCount(2, $result['tables']);
        $this->assertArrayHasKey('users', $result['tables']);
        $this->assertArrayHasKey('orders', $result['tables']);
        $this->assertSame('u', $result['tables']['users']['alias']);
        $this->assertSame('o', $result['tables']['orders']['alias']);
    }

    public function testBuildDeleteWithSingleTargetReturnsSingleTable(): void
    {
        $transformer = new DeleteTransformer();
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $result = $transformer->build($statement, $sql, ['id', 'name']);

        $this->assertSame('users', $result['table']);
        $this->assertArrayHasKey('tables', $result);
        $this->assertCount(1, $result['tables']);
        $this->assertArrayHasKey('users', $result['tables']);
    }

    public function testBuildDeleteWithUsingSyntaxMultiTable(): void
    {
        $transformer = new DeleteTransformer();
        $sql = "DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id AND o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        if (!$statement instanceof \PhpMyAdmin\SqlParser\Statements\DeleteStatement) {
            $this->fail('Expected DeleteStatement.');
        }

        $result = $transformer->build($statement, $sql, ['id', 'name']);

        $this->assertSame('users', $result['table']);
        $this->assertArrayHasKey('tables', $result);
    }
}
