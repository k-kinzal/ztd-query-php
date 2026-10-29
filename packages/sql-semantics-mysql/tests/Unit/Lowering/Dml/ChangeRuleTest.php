<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\ChangeRule;

#[CoversClass(ChangeRule::class)]
#[Medium]
final class ChangeRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerTargetRefusalReleases(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', true];
        yield '5.7' => ['mysql-5.7.44', false];
        yield '8.0' => ['mysql-8.0.44', true];
        yield '8.4' => ['mysql-8.4.7', true];
        yield '9.1' => ['mysql-9.1.0', true];
    }

    #[DataProvider('providerTargetRefusalReleases')]
    public function testMultiplePublishesCompleteUnmatchedTargetsInEveryRelease(string $release, bool $aborts): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze('DELETE QUICK x.* FROM t AS y WHERE y.a = 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        self::assertTrue($statement->hasExecutionCondition());
        self::assertSame('DELETE QUICK x.* FROM t AS y WHERE y.a = 1', $statement->toString());
    }


    #[DataProvider('providerTargetRefusalReleases')]
    public function testUpdateRetainsNonScalarAssignmentsInsideAStoredBody(string $release, bool $aborts): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() IF @active THEN UPDATE t SET a=ROW(1,2),b=DEFAULT WHERE c=3; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->body);
        $write = $operation->body->branches[0]->statements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Update::class, $write);
        self::assertCount(2, $write->assignments);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment::class, $write->assignments[0]);
        self::assertStringContainsString('UPDATE t SET a = ROW(1, 2), b = DEFAULT WHERE c = 3', $operation->toString());
    }

    #[DataProvider('providerUpdateCollisions')]
    public function testUpdateRetainsTheCompleteInputWithRepeatedNames(string $release, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Update::class, $statement);
        self::assertTrue($statement->hasExecutionCondition());
        self::assertCount(2, $statement->tables);
        self::assertCount(2, $statement->assignments);
        self::assertSame($sql, $statement->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUpdateCollisions(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach ([
                'UPDATE t, t SET a = 1, b = 2',
                'UPDATE LOW_PRIORITY IGNORE db.t AS x, db.u AS x SET a = 1, b = 2 WHERE a > 0',
                'UPDATE t AS x, u AS x SET a = 1, b = 2 ORDER BY a LIMIT 1',
            ] as $index => $sql) {
                yield $release->value . ' ' . $index => [$release->value, $sql];
            }
        }
    }

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
        self::assertSame('DELETE db.t.*, u FROM db.t, u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete db.t.*, u from db.t, u')->toString());
    }

    public function testTargetsRetainsAnIntrinsicRepetitionWithTheCompleteInputs(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('DELETE a, a FROM missing');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        self::assertCount(2, $statement->targets);
        self::assertTrue($statement->hasExecutionCondition());
    }

    public function testCompletedKeepsTheLegacyPrefixBeforeAnInvalidSuffix(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser('mysql-5.6.51'), 'DELETE d.a,d.a FROM', 18, $semantics->profile());
        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertSame("Not unique table/alias: 'a'", $notices[0][1]->problem->message());
    }
    public function testMultiplePublishesAnUnmatchedTargetWithItsMissingBinding(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('DELETE x FROM t AS y');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        self::assertTrue($statement->hasExecutionCondition());
        self::assertNull($statement->input->relation($statement->targets[0]));
    }

    public function testSinglePublishesTheCompleteCommonTableTarget(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('WITH c AS(SELECT 1) DELETE FROM c');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Delete::class, $statement);
        self::assertTrue($statement->hasExecutionCondition());
        self::assertSame('WITH c AS (SELECT 1) DELETE FROM c', $statement->toString());
    }

}
