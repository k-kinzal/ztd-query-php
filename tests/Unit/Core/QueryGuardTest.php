<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use ZtdQuery\QueryGuard;
use ZtdQuery\Rewrite\QueryKind;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\TestCase;

class QueryGuardTest extends TestCase
{
    public function testClassifiesReadStatements(): void
    {
        $guard = new QueryGuard();
        $this->assertSame(QueryKind::READ, $guard->classify('SELECT * FROM users'));
        $this->assertSame(QueryKind::READ, $guard->classify('WITH cte AS (SELECT 1) SELECT * FROM users'));
    }

    public function testClassifiesWriteStatements(): void
    {
        $guard = new QueryGuard();
        $this->assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('UPDATE users SET name = "A" WHERE id = 1'));
        $this->assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('DELETE FROM users WHERE id = 1'));
        $this->assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('INSERT INTO users (id, name) VALUES (1, "A")'));
        $this->assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT 1) UPDATE users SET name = "B" WHERE id = 1'));
    }

    public function testBlocksUnsupportedStatements(): void
    {
        $guard = new QueryGuard();
        $this->assertSame(QueryKind::FORBIDDEN, $guard->classify('DROP DATABASE test'));
        $this->assertSame(QueryKind::FORBIDDEN, $guard->classify('CREATE DATABASE test'));
        $this->assertSame(QueryKind::FORBIDDEN, $guard->classify('SELECT 1; SELECT 2'));
    }

    public function testClassifiesDdlStatements(): void
    {
        $guard = new QueryGuard();
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('DROP TABLE users'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('CREATE TABLE demo (id INT)'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users ADD COLUMN email VARCHAR(255)'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users DROP COLUMN email'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users MODIFY COLUMN name VARCHAR(500)'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)'));
        $this->assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users RENAME TO members'));
    }

    public function testAssertAllowedThrowsOnForbidden(): void
    {
        $guard = new QueryGuard();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZTD Write Protection');

        $guard->assertAllowed('DROP DATABASE test');
    }

    public function testAssertAllowedAcceptsParsedStatement(): void
    {
        $guard = new QueryGuard();
        $parser = new Parser('SELECT 1');
        $statement = $parser->statements[0];

        $guard->assertAllowed($statement);

        $this->assertSame(QueryKind::READ, $guard->classify('SELECT 1'));
    }
}
