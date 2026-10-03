<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(SelectExpression::class)]
#[Medium]
final class SelectExpressionTest extends TestCase
{
    public function testRenderWritesTheExpressionAndTheAlias(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a total from t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->items[0]->expression);
        self::assertSame('a', $operation->statement->items[0]->expression->name->value);
        self::assertSame('total', $operation->statement->items[0]->alias?->value);
        self::assertSame('SELECT a AS total FROM t', $operation->toString());
    }

    public function testRenderOmitsTheAliasWhenNoneWasWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a, 1 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNull($operation->statement->items[0]->alias);
        self::assertNull($operation->statement->items[1]->alias);
        self::assertSame('SELECT a, 1 FROM t', $operation->toString());
    }

    public function testRenderQuotesAnAliasThatIsAReservedWord(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a AS `select` FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('select', $operation->statement->items[0]->alias?->value);
        self::assertSame('SELECT a AS `select` FROM t', $operation->toString());
    }
}
