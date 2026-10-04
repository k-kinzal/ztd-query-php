<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey::class)]
#[Medium]
final class TablePrimaryKeyTest extends TestCase
{
    public function testKindIsPrimaryKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, PRIMARY KEY (a))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::PrimaryKey, $n3->kind());
    }

    public function testDeriveClauseReportsAMissingKeyColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, PRIMARY KEY (a, zz))', []);
        self::assertSame([
          0 => 'column "zz" named in key does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, b int, CONSTRAINT k PRIMARY KEY (a) INCLUDE (b) WITH (fillfactor = 70) USING INDEX TABLESPACE s INITIALLY DEFERRED)', []);
        self::assertSame('CREATE TABLE t (a INT, b INT, CONSTRAINT k PRIMARY KEY (a) INCLUDE (b) WITH (fillfactor = 70) USING INDEX TABLESPACE s INITIALLY DEFERRED)', $statement->toString());
    }
}
