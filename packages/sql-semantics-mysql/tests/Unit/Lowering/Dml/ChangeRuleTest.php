<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\ChangeRule;

#[CoversClass(ChangeRule::class)]
#[Medium]
final class ChangeRuleTest extends TestCase
{
    public function testUpdateLowersEveryGeneration(): void
    {
        self::assertSame('UPDATE t SET a = 1 WHERE b ORDER BY c LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('update t set a = 1 where b order by c limit 1')->toString());
        self::assertSame('WITH c AS (SELECT 1) UPDATE t SET a = 1', (new Semantics(Dialect::MySql))->analyze('with c as (select 1) update t set a = 1')->toString());
    }

    public function testDeleteLowersSingleAndMultipleForms(): void
    {
        self::assertSame('DELETE FROM t AS x PARTITION (p) WHERE x.a = 1', (new Semantics(Dialect::MySql))->analyze('delete from t as x partition (p) where x.a = 1')->toString());
        self::assertSame('WITH c AS (SELECT 1) DELETE FROM t USING t, c', (new Semantics(Dialect::MySql))->analyze('with c as (select 1) delete from t using t, c')->toString());
    }

    public function testLegacyDeleteLowersTheForms(): void
    {
        self::assertSame('DELETE t FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete t from t')->toString());
        self::assertSame('DELETE FROM t LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete from t limit 1')->toString());
    }

    public function testOptionsLowersTheModifiers(): void
    {
        self::assertSame('DELETE LOW_PRIORITY FROM t', (new Semantics(Dialect::MySql))->analyze('delete low_priority from t')->toString());
    }

    public function testLowPriorityLowersTheModifier(): void
    {
        self::assertSame('UPDATE LOW_PRIORITY t SET a = 1', (new Semantics(Dialect::MySql))->analyze('update low_priority t set a = 1')->toString());
    }

    public function testLimitLowersBothRules(): void
    {
        self::assertSame('UPDATE t SET a = 1 LIMIT ?', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('update t set a = 1 limit ?')->toString());
        self::assertSame('UPDATE t SET a = 1 LIMIT 4', (new Semantics(Dialect::MySql))->analyze('update t set a = 1 limit 4')->toString());
    }

    public function testWildTargetsLowersQualifiedNames(): void
    {
        self::assertSame('DELETE db.t, u FROM db.t, u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete db.t.*, u from db.t, u')->toString());
    }
}
