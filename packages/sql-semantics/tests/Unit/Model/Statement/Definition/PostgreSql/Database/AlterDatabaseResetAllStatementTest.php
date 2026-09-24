<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseResetAllStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDatabaseResetAllStatement::class)]
#[Medium]
final class AlterDatabaseResetAllStatementTest extends TestCase
{
    public function testWithOriginRetainsTheClearedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET ALL');
        self::assertInstanceOf(AlterDatabaseResetAllStatement::class, $statement);
        self::assertSame('app', $statement->withOrigin($statement->origin)->name);
    }

    public function testWithNameReplacesTheClearedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET ALL');
        self::assertInstanceOf(AlterDatabaseResetAllStatement::class, $statement);
        self::assertSame('ALTER DATABASE "other" RESET ALL', $statement->withName('other')->toString());
    }
}
