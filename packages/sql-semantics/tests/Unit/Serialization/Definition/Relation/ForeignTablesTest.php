<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\ForeignTables;

#[CoversClass(ForeignTables::class)]
#[Medium]
final class ForeignTablesTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(ForeignTables::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['CREATE FOREIGN TABLE f (a integer) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (id WITH OPTIONS NOT NULL) DEFAULT SERVER s'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($statement->toString(), ForeignTables::write($statement)?->toString());
    }

    public function testServerWritesTheServerAndOptions(): void
    {
        self::assertSame('SERVER "s"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ForeignTables::server('s', []))));
    }

    public function testColumnWritesTheOverridesAfterWithOptions(): void
    {
        self::assertSame('"a" WITH OPTIONS NOT NULL', ForeignTables::column(new Relation\Foreign\PartitionColumn('a', \SqlSemantics\Type\Nullability::NotNull))->toString());
    }
}
