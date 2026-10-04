<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey::class)]
#[Medium]
final class ColumnPrimaryKeyTest extends TestCase
{
    public function testKindIsPrimaryKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int PRIMARY KEY)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey::class, $n4);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::PrimaryKey, $n4->kind());
    }

    public function testDeriveClauseDerivesTheStorageParameters(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int PRIMARY KEY WITH (fillfactor = 70))', []);
        self::assertSame(0, count($statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CONSTRAINT k PRIMARY KEY WITH (fillfactor = 70) USING INDEX TABLESPACE s)', []);
        self::assertSame('CREATE TABLE t (a INT CONSTRAINT k PRIMARY KEY WITH (fillfactor = 70) USING INDEX TABLESPACE s)', $statement->toString());
    }
}
