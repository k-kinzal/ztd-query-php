<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\MaintenanceRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testStatementLowersEveryMaintenanceStatement(): void
    {
        self::assertSame('ANALYZE TABLE t', (new Inspection('mysql-5.6.51'))->inspect('analyze table t')->toString());
    }

    public function testTablesLowersQualifiedNames(): void
    {
        self::assertSame('OPTIMIZE TABLE a.b, c', (new Inspection())->inspect('optimize table a.b, c')->toString());
    }

    public function testCheckOptionsKeepsTheWrittenOrder(): void
    {
        self::assertSame('CHECK TABLE t EXTENDED QUICK', (new Inspection('mysql-5.7.44'))->inspect('check table t extended quick')->toString());
    }

    public function testRepairOptionsLowersLeftRecursiveLists(): void
    {
        self::assertSame('REPAIR TABLE t USE_FRM QUICK', (new Inspection())->inspect('repair table t use_frm quick')->toString());
    }

    public function testOptionsLowersAnAbsentList(): void
    {
        self::assertSame('CHECK TABLE t', (new Inspection())->inspect('check table t')->toString());
    }

    public function testChecksumLowersTheMode(): void
    {
        self::assertSame('CHECKSUM TABLE t EXTENDED', (new Inspection('mysql-5.6.51'))->inspect('checksum table t extended')->toString());
    }

    public function testHistogramLowersDrop(): void
    {
        self::assertSame('ANALYZE TABLE t DROP HISTOGRAM ON a', (new Inspection('mysql-8.0.44'))->inspect('analyze table t drop histogram on a')->toString());
    }

    public function testUpdateLowersTheBucketsOf80(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 9 BUCKETS', (new Inspection('mysql-8.0.44'))->inspect('analyze table t update histogram on a with 9 buckets')->toString());
    }

    public function testOptionalBucketsLowersAnAbsentCount(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a MANUAL UPDATE', (new Inspection('mysql-8.4.7'))->inspect('analyze table t update histogram on a manual update')->toString());
    }

    public function testBucketsKeepsTheNumber(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 0010 BUCKETS', (new Inspection())->inspect('analyze table t update histogram on a with 0010 buckets')->toString());
    }

    public function testAutomaticLowersAutoUpdate(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a AUTO UPDATE', (new Inspection('mysql-9.1.0'))->inspect('analyze table t update histogram on a auto update')->toString());
    }
}
