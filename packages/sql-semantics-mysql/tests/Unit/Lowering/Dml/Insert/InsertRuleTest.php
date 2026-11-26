<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\InsertRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InsertRule::class)]
#[Medium]
final class InsertRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('INSERT INTO t SET a = 1 ON DUPLICATE KEY UPDATE a = 2', (new Inspection('mysql-5.7.44'))->inspect('insert t set a = 1 on duplicate key update a = 2')->toString());
        self::assertSame('REPLACE INTO t SELECT 1', (new Inspection())->inspect('replace t select 1')->toString());
        self::assertSame('INSERT INTO t (a) VALUES (1) AS n ON DUPLICATE KEY UPDATE a = n.a', (new Inspection())->inspect('insert t (a) values (1) as n on duplicate key update a = n.a')->toString());
    }

    public function testLegacyLowersInsertAndReplace(): void
    {
        self::assertSame('INSERT INTO t SET a = 1', (new Inspection('mysql-5.6.51'))->inspect('insert into t set a = 1')->toString());
        self::assertSame('REPLACE INTO t SELECT 1', (new Inspection('mysql-5.6.51'))->inspect('replace t select 1')->toString());
    }

    public function testBuildChoosesRowsOrQuery(): void
    {
        self::assertSame('INSERT INTO t VALUES (1)', (new Inspection())->inspect('insert t values (1)')->toString());
        self::assertSame('INSERT INTO t (a) TABLE u', (new Inspection())->inspect('insert t (a) table u')->toString());
    }

    public function testTargetLowersTheLegacyTable(): void
    {
        self::assertSame('INSERT INTO db.t PARTITION (p) VALUES (1)', (new Inspection('mysql-5.6.51'))->inspect('insert db.t partition (p) values (1)')->toString());
    }

    public function testPriorityLowersEveryModifier(): void
    {
        self::assertSame('INSERT LOW_PRIORITY INTO t VALUES (1)', (new Inspection())->inspect('insert low_priority t values (1)')->toString());
        self::assertSame('REPLACE LOW_PRIORITY INTO t VALUES (1)', (new Inspection('mysql-5.7.44'))->inspect('replace low_priority t values (1)')->toString());
    }

    public function testAliasLowersTheColumnNames(): void
    {
        self::assertSame('INSERT INTO t VALUES (1) AS n (m) ON DUPLICATE KEY UPDATE a = m', (new Inspection())->inspect('insert t values (1) as n (m) on duplicate key update a = m')->toString());
    }

    public function testUpdatesLowersEveryGeneration(): void
    {
        self::assertSame('INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE a = 1', (new Inspection('mysql-5.6.51'))->inspect('insert t values (1) on duplicate key update a = 1')->toString());
    }
}
