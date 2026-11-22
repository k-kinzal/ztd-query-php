<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\ChangeRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ChangeRule::class)]
#[Medium]
final class ChangeRuleTest extends TestCase
{
    public function testUpdateLowersEveryGeneration(): void
    {
        self::assertSame('UPDATE t SET a = 1 WHERE b ORDER BY c LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('update t set a = 1 where b order by c limit 1')->toString());
        self::assertSame('WITH c AS (SELECT 1) UPDATE t SET a = 1', (new Inspection())->inspect('with c as (select 1) update t set a = 1')->toString());
    }

    public function testDeleteLowersSingleAndMultipleForms(): void
    {
        self::assertSame('DELETE FROM t AS x PARTITION (p) WHERE x.a = 1', (new Inspection())->inspect('delete from t as x partition (p) where x.a = 1')->toString());
        self::assertSame('WITH c AS (SELECT 1) DELETE FROM t USING t, c', (new Inspection())->inspect('with c as (select 1) delete from t using t, c')->toString());
    }

    public function testLegacyDeleteLowersTheForms(): void
    {
        self::assertSame('DELETE t FROM t', (new Inspection('mysql-5.6.51'))->inspect('delete t from t')->toString());
        self::assertSame('DELETE FROM t LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('delete from t limit 1')->toString());
    }

    public function testOptionsLowersTheModifiers(): void
    {
        self::assertSame('DELETE LOW_PRIORITY FROM t', (new Inspection())->inspect('delete low_priority from t')->toString());
    }

    public function testLowPriorityLowersTheModifier(): void
    {
        self::assertSame('UPDATE LOW_PRIORITY t SET a = 1', (new Inspection())->inspect('update low_priority t set a = 1')->toString());
    }

    public function testLimitLowersBothRules(): void
    {
        self::assertSame('UPDATE t SET a = 1 LIMIT ?', (new Inspection('mysql-5.6.51'))->inspect('update t set a = 1 limit ?')->toString());
        self::assertSame('UPDATE t SET a = 1 LIMIT 4', (new Inspection())->inspect('update t set a = 1 limit 4')->toString());
    }

    public function testWildTargetsLowersQualifiedNames(): void
    {
        self::assertSame('DELETE db.t, u FROM db.t, u', (new Inspection('mysql-5.6.51'))->inspect('delete db.t.*, u from db.t, u')->toString());
    }
}
