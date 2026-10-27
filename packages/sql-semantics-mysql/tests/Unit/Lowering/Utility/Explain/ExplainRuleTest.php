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
        self::assertSame('EXPLAIN FORMAT = json FOR CONNECTION 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('explain format = json for connection 1')->toString());
        self::assertSame('EXPLAIN PARTITIONS INSERT INTO t VALUES (1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('explain partitions insert into t values (1)')->toString());
    }

    public function testModernLowersTheOptions(): void
    {
        self::assertSame('EXPLAIN ANALYZE FORMAT = json INTO @v FOR DATABASE db SELECT 1', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('explain analyze format=json into @v for database db select 1')->toString());
    }

    public function testModernRefusesConnectionAnalysisBeforePublishingAStatement(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('EXPLAIN ANALYZE FOR CONNECTION is not supported.');

        (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE FOR CONNECTION 1');
    }

    public function testModernRefusesConnectionIntoBeforePublishingAStatement(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('EXPLAIN FOR CONNECTION does not support the INTO clause.');

        (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT=JSON INTO @a FOR CONNECTION 1');
    }

    public function testExplainedLowersThroughTheOwningFamily(): void
    {
        self::assertSame('EXPLAIN UPDATE t SET a = 1', (new Semantics(Dialect::MySql))->analyze('explain update t set a = 1')->toString());
    }
    public function testDeferredRetainsTheCompleteExplainedRead(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT ROW(1,2)");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain::class, $operation->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->body->statement);
    }

    public function testDeferredRetainsTheExplanationAroundARefusedQueryWrite(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('EXPLAIN FOR DATABASE d REPLACE INTO t SELECT *');
            self::fail('A wildcard-only read needs deferred preparation.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain::class, $failure->problem);
            self::assertSame('d', $failure->problem->explanation->database?->value);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery::class, $failure->problem->explanation->statement);
            self::assertTrue($failure->problem->explanation->statement->into->replace);
        }
    }
    public function testTargetRejectsAnInvalidModernOutputName(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("User variable name '' is illegal");
        (new Semantics(Dialect::MySql))->analyze("EXPLAIN FORMAT=JSON INTO @'' SELECT 1");
    }
}
