<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Modern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Expression\PrimaryRule;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(PrimaryRule::class)]
#[Medium]
final class PrimaryRuleTest extends TestCase
{
    public function testPrimaryLowersEveryPrimary(): void
    {
        self::assertInstanceOf(ExplicitTable::class, (new Semantics(Dialect::MySql))->analyze('TABLE t')->statement);
        self::assertInstanceOf(Select::class, (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement);
    }

    public function testSpecificationLowersEveryClause(): void
    {
        self::assertSame('SELECT a INTO @x FROM t WHERE 1 GROUP BY a HAVING 1 WINDOW w AS () QUALIFY 1', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a into @x from t where 1 group by a having 1 window w as () qualify 1')->toString());
        self::assertSame('SELECT a INTO @x FROM t WHERE 1 GROUP BY a HAVING 1 WINDOW w AS ()', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select a into @x from t where 1 group by a having 1 window w as ()')->toString());
    }

    public function testValuesLowersTheRowsThroughTheDmlFamily(): void
    {
        self::assertSame('VALUES ROW(1), ROW(2)', (new Semantics(Dialect::MySql))->analyze('values row(1), row(2)')->toString());
    }

    public function testExplicitLowersTheTable(): void
    {
        self::assertSame('TABLE db.t', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('table db.t')->toString());
    }

    public function testWithLowersTheCommonTables(): void
    {
        self::assertSame('WITH RECURSIVE a (x) AS (SELECT 1), b AS (SELECT 2) SELECT 3', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('with recursive a (x) as (select 1), b as (select 2) select 3')->toString());
    }
}
