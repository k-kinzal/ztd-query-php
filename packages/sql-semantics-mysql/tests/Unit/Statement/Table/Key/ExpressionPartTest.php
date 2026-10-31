<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ExpressionPart::class)]
#[Medium]
final class ExpressionPartTest extends TestCase
{
    public function testDeriveKeyPartResolvesTheExpressionInTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY ((a + 1)))');
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(IndexDefinition::class, $element1);
        $part = $element1->parts[0];

        self::assertInstanceOf(ExpressionPart::class, $part);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic::class, $part->expression);
        $resolution = $part->expression->left->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[0], $resolution->slot->column);
    }

    public function testABareColumnCannotBeAnOrdinaryFunctionalKey(): void
    {
        $column = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a'));
        $this->expectExceptionMessage('A functional key expression cannot consist only of a column reference.');
        new ExpressionPart(new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($column));
    }

    public function testRenderWritesTheParenthesizedExpression(): void
    {
        self::assertSame('CREATE TABLE t (a INT, KEY ((a + 1) DESC))', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY ((a + 1) DESC))')->toString());
    }
}
