<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull::class)]
#[Medium]
final class NotNullTest extends TestCase
{
    public function testKindIsNotNull(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int NOT NULL)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull::class, $n4);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::NotNull, $n4->kind());
    }

    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int NOT NULL)', []);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $statement->declarations()[0]->columns[0]->nullability);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CONSTRAINT n NOT NULL)', []);
        self::assertSame('CREATE TABLE t (a INT CONSTRAINT n NOT NULL)', $statement->toString());
    }
}
