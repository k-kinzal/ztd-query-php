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
    public function testMultipleKeepsTheReleaseSpecificInputStoppingRule(string $release, bool $aborts): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze('DELETE x FROM t AS y');
            self::fail('An absent target must be refused.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame($aborts, $failure->aborts);
        }
    }


    #[DataProvider('providerTargetRefusalReleases')]
    public function testUpdateRetainsNonScalarAssignmentsInsideAStoredBody(string $release, bool $aborts): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() IF @active THEN UPDATE t SET a=ROW(1,2),b=DEFAULT WHERE c=3; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->statement->body);
        $write = $operation->statement->body->branches[0]->statements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate::class, $write);
        self::assertCount(2, $write->assignments);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment::class, $write->assignments[0]);
        self::assertStringContainsString('UPDATE t SET a = ROW(1, 2), b = DEFAULT WHERE c = 3', $operation->toString());
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
        self::assertSame('DELETE db.t, u FROM db.t, u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete db.t.*, u from db.t, u')->toString());
    }

    public function testTargetsRefusesAnIntrinsicRepetitionBeforeReadingRelations(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Not unique table/alias: 'a'");
        (new Semantics(Dialect::MySql))->analyze('DELETE a, a FROM missing');
    }

    public function testCompletedKeepsTheLegacyPrefixBeforeAnInvalidSuffix(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser('mysql-5.6.51'), 'DELETE d.a,d.a FROM', 18, $semantics->profile());
        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertSame("Not unique table/alias: 'a'", $notices[0][1]->problem->message());
    }
    public function testMultipleRefusesAnUnmatchedTargetWithoutPublishingAStatement(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Unknown table 'x' in MULTI DELETE");
        (new Semantics(Dialect::MySql))->analyze('DELETE x FROM t AS y');
    }

    public function testSingleRefusesAStandaloneCommonTableTarget(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        (new Semantics(Dialect::MySql))->analyze('WITH c AS(SELECT 1) DELETE FROM c');
    }

}
