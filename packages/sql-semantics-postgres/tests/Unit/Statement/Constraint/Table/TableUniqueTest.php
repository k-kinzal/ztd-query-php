<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique::class)]
#[Medium]
final class TableUniqueTest extends TestCase
{
    public function testKindIsUnique(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, UNIQUE (a))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Unique, $n3->kind());
    }

    public function testDeriveClauseReportsAMissingKeyColumnAndAnAttribute(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, UNIQUE (zz) NO INHERIT)', []);
        self::assertSame([
          0 => 'column "zz" named in key does not exist',
          1 => 'UNIQUE constraints cannot be marked NO INHERIT',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, b int, UNIQUE NULLS NOT DISTINCT (a) INCLUDE (b) WITH (fillfactor = 70) USING INDEX TABLESPACE s DEFERRABLE)', []);
        self::assertSame('CREATE TABLE t (a INT, b INT, UNIQUE NULLS NOT DISTINCT (a) INCLUDE (b) WITH (fillfactor = 70) USING INDEX TABLESPACE s DEFERRABLE)', $statement->toString());
    }
}
