<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression::class)]
#[Medium]
final class DefaultExpressionTest extends TestCase
{
    public function testKindIsDefault(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int DEFAULT 1)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression::class, $n4);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Default, $n4->kind());
    }

    public function testDeriveClauseSeesNoColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, b int DEFAULT a)', []);
        self::assertSame([
          0 => 'Column a does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CONSTRAINT d DEFAULT 1 + 2)', []);
        self::assertSame('CREATE TABLE t (a INT CONSTRAINT d DEFAULT 1 + 2)', $statement->toString());
    }

    public function testRefusesAnExpressionThatNeedsParentheses(): void
    {
        $this->expectExceptionMessage('A default is an expression without boolean, IS, pattern or subquery operators outside parentheses.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('a')]), false));
    }
}
