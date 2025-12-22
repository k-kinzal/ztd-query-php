<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Shadowing;

use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use PHPUnit\Framework\TestCase;

final class CteShadowingTest extends TestCase
{
    public function testApplyUsesSchemaColumnOrder(): void
    {
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE `users` (`id` INT, `name` VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);

        $sql = 'SELECT * FROM users';
        $rows = [
            ['name' => 'Alice', 'id' => 1],
        ];

        $result = $shadowing->apply($sql, ['users' => $rows]);

        // When column types are available from schema, values are quoted and cast to their MySQL types
        $this->assertStringStartsWith("WITH `users` AS (SELECT CAST('1' AS SIGNED) AS `id`, CAST('Alice' AS CHAR) AS `name`)", $result);
        $this->assertStringContainsString($sql, $result);
    }

    public function testApplyMergesExistingWithClause(): void
    {
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);

        $sql = 'WITH old_cte AS (SELECT 1) SELECT * FROM users';
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $result = $shadowing->apply($sql, ['users' => $rows]);

        $this->assertStringContainsString('WITH `users` AS', $result);
        $this->assertStringContainsString('old_cte AS (SELECT 1)', $result);
    }

    public function testApplySkipsWhenTableNotReferenced(): void
    {
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $shadowing = new CteShadowing(new CteGenerator(), $schema);

        $sql = 'SELECT * FROM orders';
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $result = $shadowing->apply($sql, ['users' => $rows]);

        $this->assertSame($sql, $result);
    }
}
