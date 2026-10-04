<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(ResultColumn::class)]
#[Medium]
final class ResultColumnTest extends TestCase
{
    public function testRenderWritesTheAliasWithAs(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select 1 one, a as "x y", b from t');

        self::assertInstanceOf(Select::class, $query->statement);
        $columns = $query->statement->columns;
        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertSame('one', $columns[0]->alias?->value);
        self::assertInstanceOf(ResultColumn::class, $columns[1]);
        self::assertSame('x y', $columns[1]->alias?->value);
        self::assertInstanceOf(ResultColumn::class, $columns[2]);
        self::assertNull($columns[2]->alias);
        self::assertInstanceOf(ColumnUse::class, $columns[2]->expression);
        self::assertSame('SELECT 1 AS one, a AS `x y`, b FROM t', $query->toString());
    }

    public function testRenderWritesANewlyBuiltColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new IntegerLiteral('1'), new Name('n')), new ResultColumn(new IntegerLiteral('2'))]));

        self::assertSame('SELECT 1 AS n, 2', $operation->toString());
        self::assertSame('n', $operation->field(0)->name?->value);
        self::assertNull($operation->field(1)->name);
    }
}
