<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(DefaultExpression::class)]
#[Medium]
final class DefaultExpressionTest extends TestCase
{
    public function testDeriveAttributeResolvesColumnsOfTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT DEFAULT (a + 1))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        $attribute = $element1->specification->columnAttributes()[0];

        self::assertInstanceOf(DefaultExpression::class, $attribute);
        self::assertInstanceOf(Arithmetic::class, $attribute->expression);
        $resolution = $create->facts->scalar($attribute->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[0], $resolution->slot->column);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a INT, b INT DEFAULT (a + 1))', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT DEFAULT (a + 1))')->toString());
    }
}
