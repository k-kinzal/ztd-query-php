<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteInMemoryAttachStatement;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteInMemoryAttachStatement::class)]
#[UsesClass(SqlTokenStream::class)]
final class SqliteInMemoryAttachStatementTest extends TestCase
{
    public function testAcceptsOnlyLiteralInMemoryAttachments(): void
    {
        self::assertTrue(SqliteInMemoryAttachStatement::isSafe("ATTACH ':memory:' AS db2"));
        self::assertTrue(SqliteInMemoryAttachStatement::isSafe("ATTACH DATABASE ':memory:' AS \"db2\";"));
        self::assertTrue(SqliteInMemoryAttachStatement::isSafe("ATTACH /* safe */ ':memory:' AS [db2]"));
    }

    public function testRejectsPersistentOrDynamicAttachments(): void
    {
        self::assertFalse(SqliteInMemoryAttachStatement::isSafe("ATTACH 'test.sqlite' AS db2"));
        self::assertFalse(SqliteInMemoryAttachStatement::isSafe("ATTACH ('file:' || :name) AS db2"));
        self::assertFalse(SqliteInMemoryAttachStatement::isSafe("ATTACH ':memory:' AS db2; DELETE FROM users"));
        self::assertFalse(SqliteInMemoryAttachStatement::isSafe("DETACH DATABASE db2"));
    }
}
