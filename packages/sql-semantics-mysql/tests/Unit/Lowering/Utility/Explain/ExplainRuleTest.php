<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\ExplainRule;

#[CoversClass(ExplainRule::class)]
#[Medium]
final class ExplainRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('DESCRIBE t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('describe t')->toString());
        self::assertSame('EXPLAIN REPLACE INTO t VALUES (1)', (new Semantics(Dialect::MySql))->analyze('explain replace into t values (1)')->toString());
    }

    public function testVerbAcceptsDescAndDescribe(): void
    {
        self::assertSame('EXPLAIN SELECT 1', (new Semantics(Dialect::MySql))->analyze('desc select 1')->toString());
    }

    public function testColumnLowersTheColumnPattern(): void
    {
        self::assertSame("DESCRIBE t x'61'", (new Semantics(Dialect::MySql))->analyze('describe t x\'61\'')->toString());
    }

    public function testLegacyLowersTheModifiersAndTheFormat(): void
    {
        self::assertSame('EXPLAIN FORMAT = `json` FOR CONNECTION 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('explain format = json for connection 1')->toString());
        self::assertSame('EXPLAIN PARTITIONS INSERT INTO t VALUES (1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('explain partitions insert into t values (1)')->toString());
    }

    public function testModernLowersTheOptions(): void
    {
        self::assertSame('EXPLAIN ANALYZE FORMAT = `json` INTO @v FOR DATABASE db SELECT 1', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('explain analyze format=json into @v for database db select 1')->toString());
    }

    public function testExplainedLowersThroughTheOwningFamily(): void
    {
        self::assertSame('EXPLAIN UPDATE t SET a = 1', (new Semantics(Dialect::MySql))->analyze('explain update t set a = 1')->toString());
    }
}
