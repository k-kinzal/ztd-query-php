<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use ZtdQuery\Platform\MySql\MySqlQueryTransformer;
use ZtdQuery\Schema\SchemaRegistry;
use PHPUnit\Framework\TestCase;

class MySqlQueryTransformerTest extends TestCase
{
    public function testSelectWithoutShadowDataReturnsOriginalSql(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "SELECT * FROM users";
        $result = $transformer->transform($sql, []);
        $this->assertEquals($sql, $result);
    }

    public function testSelectWithShadowDataPrependsCte(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "SELECT * FROM users";
        $tableData = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
            ]
        ];

        $result = $transformer->transform($sql, $tableData);

        $this->assertStringContainsString("WITH", $result);
        $this->assertStringContainsString("`users` AS (SELECT CAST(1 AS SIGNED) AS `id`, CAST('Alice' AS CHAR) AS `name`)", $result);
        $this->assertStringContainsString($sql, $result);
    }

    public function testSelectWithExistingCteAndShadowDataCombinesThem(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "WITH old_cte AS (SELECT 1) SELECT * FROM users";
        $tableData = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
            ]
        ];

        $result = $transformer->transform($sql, $tableData);
        $this->assertStringContainsString("WITH `users` AS (SELECT CAST(1 AS SIGNED) AS `id`, CAST('Alice' AS CHAR) AS `name`),", $result);
        $this->assertStringContainsString("old_cte AS (SELECT 1)", $result);
    }

    public function testUpdateIsTransformedToSelect(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $tableData = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
            ]
        ];

        $result = $transformer->transform($sql, $tableData);

        $this->assertStringStartsWith("WITH", $result);
        $this->assertStringContainsString("SELECT 'Bob' AS `name`", $result);
        $this->assertStringContainsString("`users`.`id`", $result);
        $this->assertStringContainsString("FROM `users`", $result);
        $this->assertStringContainsString("WHERE id = 1", $result);
    }

    public function testDeleteIsTransformedToSelect(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "DELETE FROM users WHERE id = 1";
        $tableData = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
            ]
        ];

        $result = $transformer->transform($sql, $tableData);

        $this->assertStringStartsWith("WITH", $result);
        $this->assertStringContainsString("SELECT `users`.`id` AS `id`", $result);
        $this->assertStringContainsString("FROM users", $result);
        $this->assertStringContainsString("WHERE id = 1", $result);
    }

    public function testSafetyBlockDDL(): void
    {
        $transformer = new MySqlQueryTransformer();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("ZTD Write Protection");

        $sql = "DROP DATABASE test";
        $tableData = [
            'users' => [['id' => 1]]
        ];

        $transformer->transform($sql, $tableData);
    }

    public function testInsertIsTransformedToSelect(): void
    {
        $transformer = new MySqlQueryTransformer();
        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice')";

        $result = $transformer->transform($sql, []);

        $this->assertStringStartsWith('SELECT', $result);
        $this->assertStringContainsString("1 AS `id`", $result);
        $this->assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testInsertWithoutColumnsUsesSchema(): void
    {
        $schema = new SchemaRegistry();
        $schema->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $transformer = new MySqlQueryTransformer(null, $schema);

        $sql = "INSERT INTO users VALUES (1, 'Alice')";
        $result = $transformer->transform($sql, []);

        $this->assertStringStartsWith('SELECT', $result);
        $this->assertStringContainsString("1 AS `id`", $result);
        $this->assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testInsertWithoutColumnsAndSchemaThrows(): void
    {
        $transformer = new MySqlQueryTransformer();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZTD Write Protection');

        $sql = "INSERT INTO users VALUES (1, 'Alice')";
        $transformer->transform($sql, []);
    }

    public function testInsertOnDuplicateKeyUpdateIsSupported(): void
    {
        $transformer = new MySqlQueryTransformer();

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Bob'";
        $result = $transformer->transform($sql, []);

        $this->assertStringContainsString('SELECT', $result);
        $this->assertStringContainsString("1 AS `id`", $result);
        $this->assertStringContainsString("'Alice' AS `name`", $result);
    }
}
