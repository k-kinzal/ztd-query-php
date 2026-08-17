<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\ReadOnlyDiagnosticStatement;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ReadOnlyDiagnosticStatement::class)]
#[UsesClass(SqlTokenStream::class)]
final class ReadOnlyDiagnosticStatementTest extends TestCase
{
    public function testRecognizesReadOnlyDiagnostics(): void
    {
        self::assertTrue(ReadOnlyDiagnosticStatement::isSafe('EXPLAIN SELECT * FROM users'));
        self::assertTrue(ReadOnlyDiagnosticStatement::isSafe('EXPLAIN QUERY PLAN SELECT * FROM users'));
        self::assertTrue(ReadOnlyDiagnosticStatement::isSafe('EXPLAIN (ANALYZE TRUE, FORMAT JSON) SELECT * FROM users'));
        self::assertTrue(ReadOnlyDiagnosticStatement::isSafe('DESCRIBE users'));
        self::assertTrue(ReadOnlyDiagnosticStatement::isSafe('SHOW CREATE TABLE users'));
    }

    public function testRejectsExecutableOrMultipleStatements(): void
    {
        self::assertFalse(ReadOnlyDiagnosticStatement::isSafe('EXPLAIN ANALYZE UPDATE users SET active = FALSE'));
        self::assertFalse(ReadOnlyDiagnosticStatement::isSafe('EXPLAIN ANALYZE EXECUTE mutate_users'));
        self::assertFalse(ReadOnlyDiagnosticStatement::isSafe('SHOW TABLES; DELETE FROM users'));
        self::assertFalse(ReadOnlyDiagnosticStatement::isSafe('UPDATE users SET active = FALSE'));
    }
}
