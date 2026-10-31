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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadConditions')]
    public function testDeferredRetainsCompleteReadConditions(string $version, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $version))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement->statement);
        self::assertTrue($statement->statement->hasExecutionCondition());
        self::assertSame($sql, $statement->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerReadConditions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['DESCRIBE SELECT 1 UNION SELECT 1, 2'] as $sql) {
                yield $version . ' ' . $sql => ['mysql-' . $version, $sql];
            }
        }
    }

    public function testDeferredRefusesAStandaloneStarWithoutTablesAndStoresIt(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT = JSON SELECT *');
            self::fail('A star needs tables.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame('No tables used', $failure->problem->message());
        }
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DESC SELECT *');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $stored->body);
    }

    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('DESCRIBE t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('describe t')->toString());
        self::assertSame('EXPLAIN REPLACE INTO t VALUES (1)', (new Semantics(Dialect::MySql))->analyze('explain replace into t values (1)')->toString());
    }

    public function testVerbAcceptsDescAndDescribe(): void
    {
        self::assertSame('DESC SELECT 1', (new Semantics(Dialect::MySql))->analyze('desc select 1')->toString());
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

    public function testModernRetainsConnectionAnalysisForExecution(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE FOR CONNECTION 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection::class, $statement);
        self::assertTrue($statement->analyze);
        self::assertSame('EXPLAIN ANALYZE FOR CONNECTION 1', $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain::class, $statement->diagnostics()[0]);
    }

    public function testModernRetainsConnectionIntoForExecution(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT=JSON INTO @a FOR CONNECTION 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection::class, $statement);
        self::assertSame('a', $statement->into?->name->value);
        self::assertSame('EXPLAIN FORMAT = JSON INTO @a FOR CONNECTION 1', $statement->toString());
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

    public function testDeferredRetainsTheExplanationAroundAConditionalQueryWrite(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FOR DATABASE d REPLACE INTO t SELECT *');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $statement);
        self::assertSame('d', $statement->database?->value);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $statement->statement);
        self::assertTrue($statement->statement->hasExecutionCondition());
        self::assertTrue($statement->statement->into->replace);
    }
    public function testTargetRejectsAnInvalidModernOutputName(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("User variable name '' is illegal");
        (new Semantics(Dialect::MySql))->analyze("EXPLAIN FORMAT=JSON INTO @'' SELECT 1");
    }
}
