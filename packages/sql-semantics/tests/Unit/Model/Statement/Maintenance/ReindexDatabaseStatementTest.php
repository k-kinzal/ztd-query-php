<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\DatabaseIndexScope;
use SqlSemantics\Model\Maintenance\ReindexOptions;
use SqlSemantics\Model\Statement\Maintenance\ReindexDatabaseStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexDatabaseStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ReindexDatabaseStatementTest extends TestCase
{
    #[TestWith(['REINDEX SYSTEM', DatabaseIndexScope::SystemTables])]
    #[TestWith(['REINDEX DATABASE', DatabaseIndexScope::UserTables])]
    public function testKeepsTheCurrentDatabaseImplicit(string $sql, DatabaseIndexScope $selection): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(ReindexDatabaseStatement::class, $statement);
        self::assertSame($selection, $statement->selection);
        self::assertNull($statement->database);
        self::assertSame($sql, $statement->toString());
    }

    public function testWithOriginPreservesTheExplicitDatabaseAndRebuildOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REINDEX (VERBOSE true, TABLESPACE ts) DATABASE CONCURRENTLY app');
        self::assertInstanceOf(ReindexDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame('app', $copy->database);
        self::assertTrue($copy->options->concurrently);
        self::assertTrue($copy->options->verbose);
        self::assertSame('ts', $copy->options->tablespace);
        self::assertSame('REINDEX(CONCURRENTLY, VERBOSE, TABLESPACE "ts") DATABASE "app"', $copy->toString());
    }

    public function testRejectsConcurrentSystemIndexRebuildingBeforeSerialization(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REINDEX SYSTEM');
        self::assertInstanceOf(ReindexDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ReindexDatabaseStatement($statement->origin, DatabaseIndexScope::SystemTables, options: new ReindexOptions(concurrently: true));
    }
}
