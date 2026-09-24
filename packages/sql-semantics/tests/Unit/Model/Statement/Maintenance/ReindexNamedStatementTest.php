<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\ReindexNamedStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexNamedStatement::class)]
#[Medium]
final class ReindexNamedStatementTest extends TestCase
{
    public function testBindsTheQualifiedRebuildTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)', 'CREATE INDEX ix ON t(id)')))->bind('REINDEX main.ix');
        self::assertInstanceOf(ReindexNamedStatement::class, $statement);
        self::assertSame(['main', 'ix'], $statement->target->parts);
        self::assertSame(StatementKind::Reindex, $statement->kind);
        self::assertSame('REINDEX "main"."ix"', $statement->toString());
    }

    public function testWithOriginPreservesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX ix');
        self::assertInstanceOf(ReindexNamedStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->target, $copy->target);
        self::assertSame('REINDEX "ix"', $copy->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX ix');
        self::assertInstanceOf(ReindexNamedStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ReindexNamedStatement(new Origin('s0', $statement->source, Dialect::MySql), $statement->target);
    }
}
