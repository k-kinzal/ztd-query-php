<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\ExplainRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExplainRule::class)]
#[Medium]
final class ExplainRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('DESCRIBE t', (new Inspection('mysql-5.6.51'))->inspect('describe t')->toString());
        self::assertSame('EXPLAIN REPLACE INTO t VALUES (1)', (new Inspection())->inspect('explain replace into t values (1)')->toString());
    }

    public function testVerbAcceptsDescAndDescribe(): void
    {
        self::assertSame('EXPLAIN SELECT 1', (new Inspection())->inspect('desc select 1')->toString());
    }

    public function testColumnLowersTheColumnPattern(): void
    {
        self::assertSame("DESCRIBE t x'61'", (new Inspection())->inspect('describe t x\'61\'')->toString());
    }

    public function testLegacyLowersTheModifiersAndTheFormat(): void
    {
        self::assertSame('EXPLAIN FORMAT = `json` FOR CONNECTION 1', (new Inspection('mysql-5.7.44'))->inspect('explain format = json for connection 1')->toString());
        self::assertSame('EXPLAIN PARTITIONS INSERT INTO t VALUES (1)', (new Inspection('mysql-5.6.51'))->inspect('explain partitions insert into t values (1)')->toString());
    }

    public function testModernLowersTheOptions(): void
    {
        self::assertSame('EXPLAIN ANALYZE FORMAT = `json` INTO @v FOR DATABASE db SELECT 1', (new Inspection('mysql-9.1.0'))->inspect('explain analyze format=json into @v for database db select 1')->toString());
    }

    public function testExplainedLowersThroughTheOwningFamily(): void
    {
        self::assertSame('EXPLAIN UPDATE t SET a = 1', (new Inspection())->inspect('explain update t set a = 1')->toString());
    }
}
