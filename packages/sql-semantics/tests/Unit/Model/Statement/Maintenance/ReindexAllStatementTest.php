<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\ReindexAllStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexAllStatement::class)]
#[Medium]
final class ReindexAllStatementTest extends TestCase
{
    public function testBindsAnUnqualifiedRebuild(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX');
        self::assertInstanceOf(ReindexAllStatement::class, $statement);
        self::assertSame(StatementKind::Reindex, $statement->kind);
        self::assertSame('REINDEX', $statement->toString());
    }

    public function testWithOriginReplacesProvenanceOnly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX');
        self::assertInstanceOf(ReindexAllStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame('REINDEX', $copy->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('REINDEX');
        self::assertInstanceOf(ReindexAllStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ReindexAllStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}
