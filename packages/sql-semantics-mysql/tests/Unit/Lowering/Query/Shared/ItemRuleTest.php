<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;

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
        self::assertSame('SELECT a + 1 AS b FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a + 1 b from t')->toString());
    }

    public function testAliasLowersNamesAndStrings(): void
    {
        self::assertSame('SELECT a AS x, b AS y FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a \'x\', b as `y` from t')->toString());
        self::assertSame('SELECT a AS x FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a as \'x\' from t')->toString());
    }
}
