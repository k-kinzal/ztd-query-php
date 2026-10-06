<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ItemRule::class)]
#[Medium]
final class ItemRuleTest extends TestCase
{
    public function testOptionsLowersEveryOption(): void
    {
        self::assertSame('SELECT STRAIGHT_JOIN HIGH_PRIORITY DISTINCT SQL_SMALL_RESULT SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_CALC_FOUND_ROWS SQL_NO_CACHE a FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select straight_join high_priority distinct sql_small_result sql_big_result sql_buffer_result sql_calc_found_rows sql_no_cache a from t')->toString());
        self::assertSame('SELECT ALL a FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select all a from t')->toString());
    }

    public function testItemsLowersTheStarAndTheItems(): void
    {
        self::assertSame('SELECT *, t.*, db.t.*, a FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select *, t.*, db.t.*, a from t')->toString());
        self::assertSame('SELECT *, t.* FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select *, t.* from t')->toString());
    }

    public function testItemLowersOneItem(): void
    {
        self::assertSame('SELECT a + 1 b FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a + 1 b from t')->toString());
    }

    public function testAliasLowersNamesAndStrings(): void
    {
        self::assertSame('SELECT a x, b AS y FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a \'x\', b as `y` from t')->toString());
        self::assertSame('SELECT a AS x FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a as \'x\' from t')->toString());
    }

    public function testExpressionKeepsTheLayoutOfAnItemNamedAfterItsText(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1+1, 1 + 1, a, 2 AS b FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['1+1', null, null, null], array_map(static fn (object $item): ?string => $item instanceof SelectExpression ? $item->layout?->text() : null, $operation->statement->items));
        self::assertSame('SELECT 1+1, 1 + 1, a, 2 AS b FROM t', $operation->toString());
    }

    public function testMarkAnswersWhetherAnAliasIsWrittenAfterAs(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 1 x, 2 AS y, 3 'z', 4 FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([AliasMark::Bare, AliasMark::As, AliasMark::Bare, AliasMark::As], array_map(static fn (object $item): AliasMark => $item instanceof SelectExpression ? $item->mark : AliasMark::Equals, $operation->statement->items));
    }
}
