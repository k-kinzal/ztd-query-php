<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\MaintenanceRule;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testStatementLowersEveryMaintenanceStatement(): void
    {
        self::assertSame('ANALYZE TABLE t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('analyze table t')->toString());
    }

    public function testTablesLowersQualifiedNames(): void
    {
        self::assertSame('OPTIMIZE TABLE a.b, c', (new Semantics(Dialect::MySql))->analyze('optimize table a.b, c')->toString());
    }

    public function testCheckOptionsKeepsTheWrittenOrder(): void
    {
        self::assertSame('CHECK TABLE t EXTENDED QUICK', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('check table t extended quick')->toString());
    }

    public function testRepairOptionsLowersLeftRecursiveLists(): void
    {
        self::assertSame('REPAIR TABLE t USE_FRM QUICK', (new Semantics(Dialect::MySql))->analyze('repair table t use_frm quick')->toString());
    }

    public function testOptionsLowersAnAbsentList(): void
    {
        self::assertSame('CHECK TABLE t', (new Semantics(Dialect::MySql))->analyze('check table t')->toString());
    }

    public function testChecksumLowersTheMode(): void
    {
        self::assertSame('CHECKSUM TABLE t EXTENDED', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('checksum table t extended')->toString());
    }

    public function testHistogramLowersDrop(): void
    {
        self::assertSame('ANALYZE TABLE t DROP HISTOGRAM ON a', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('analyze table t drop histogram on a')->toString());
    }

    public function testUpdateLowersTheBucketsOf80(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 9 BUCKETS', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('analyze table t update histogram on a with 9 buckets')->toString());
    }

    public function testOptionalBucketsLowersAnAbsentCount(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a MANUAL UPDATE', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('analyze table t update histogram on a manual update')->toString());
    }

    public function testBucketsKeepsTheNumber(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 0010 BUCKETS', (new Semantics(Dialect::MySql))->analyze('analyze table t update histogram on a with 0010 buckets')->toString());
    }

    public function testAutomaticLowersAutoUpdate(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a AUTO UPDATE', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('analyze table t update histogram on a auto update')->toString());
    }
}
