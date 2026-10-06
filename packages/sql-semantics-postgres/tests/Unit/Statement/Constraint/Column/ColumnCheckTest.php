<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck::class)]
#[Medium]
final class ColumnCheckTest extends TestCase
{
    public function testKindIsCheck(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CHECK (a > 0))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck::class, $n4);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Check, $n4->kind());
    }

    public function testDeriveClauseResolvesTheConditionAgainstTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CHECK (a > 0))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck::class, $n4);
        $n5 = $n4->condition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation::class, $n5);
        $n6 = $n5->left;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference::class, $n6);
        $n7 = $statement->facts->scalar($n6)->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $n7);
        self::assertSame(true, $n7->slot->column === $statement->declarations()[0]->columns[0]);
    }

    public function testDeriveClauseReportsAConditionThatIsNotBoolean(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CHECK (a + 1))', []);
        self::assertSame([
          0 => 'argument of CHECK must be type boolean',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CONSTRAINT c CHECK (a > 0) NO INHERIT)', []);
        self::assertSame('CREATE TABLE t (a INT CONSTRAINT c CHECK (a > 0) NO INHERIT)', $statement->toString());
    }
}
