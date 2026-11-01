<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Projection;

use MySqlMemory\Evaluation\Compile\Projection\ComparedOutput;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComparedOutput::class)]
#[Small]
final class ComparedOutputTest extends TestCase
{
    public function testComparatorReadsStoredValuesForOrderedQuantification(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT 0x0a <= ANY (TABLE t),10 <= ANY (SELECT 0x37),0x37 <= ANY (SELECT i FROM t)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
    }

    public function testFixedKeepsNumericLiteralInterpretationForConstantProjections(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT 0x37 <= ANY (SELECT 7 FROM t),0x37 <= ANY (SELECT MAX(7) FROM t)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
    }

    public function testFixedKeepsEverySetBranchInTheConstantDecision(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT 0x37 <= ANY (SELECT 7 UNION ALL SELECT 10),0x37 <= ANY (SELECT 7 UNION ALL SELECT i FROM t)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
    }

    public function testFixedFollowsTheResolvedExpressionBehindAStar(): void
    {
        $result = (new Instance())->connect()->query('SELECT 0x37 <= ANY (SELECT * FROM (SELECT 7 AS i) q)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
    }

    public function testFixedFollowsTheResolvedPositionThroughDerivedSetOutputs(): void
    {
        $result = (new Instance())->connect()->query('SELECT 0x37 <= ANY (SELECT i FROM (SELECT 7 AS i UNION ALL SELECT 10) q)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
    }

    public function testBinaryKeepsDirectLiteralDomainsForMembershipSetBranches(): void
    {
        $result = (new Instance())->connect()->query("SELECT 7 = ANY (SELECT 0x37 UNION ALL SELECT 0x37),7 <> ALL (SELECT 0x37 UNION ALL SELECT 0x37),7 = ANY (SELECT _binary'7' UNION ALL SELECT _binary'7')")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '1']], $result->rows);
    }
    public function testConstantPreservesLegacyNullsBeforeWhereButAfterHaving(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT 7 <= ANY(SELECT IF(1,NULL,7) FROM t WHERE FALSE),7 <= ANY(SELECT NULL FROM t HAVING FALSE),7 <= ANY(SELECT @v FROM t WHERE FALSE)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0', null]], $result->rows);
    }

    public function testSkipNullPreservesLegacyHavingComparisonRoles(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT 7 <= ANY(SELECT IF(1,NULL,7) FROM t HAVING TRUE),7 <= ANY(SELECT NULL FROM t HAVING TRUE),7 <= ANY(SELECT @v FROM t HAVING TRUE)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null, null]], $result->rows);
    }

    public function testSkipNullKeepsNullOperandsAndTablelessHaving(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT); INSERT INTO t VALUES(7)');
        $result = $session->query('SELECT NULL <= ANY(SELECT 7 FROM t HAVING TRUE),7 <= ANY(SELECT IF(1,NULL,7) FROM DUAL HAVING TRUE),7 <= ANY(SELECT @v FROM t HAVING TRUE)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, '0']], $result->rows);
    }

    public function testConstantRetainsMySql57FiltersBeforeAnEmptyInputNullComparison(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(i INT)');
        $result = $session->query('SELECT 7 IN(SELECT NULL FROM t),7 IN(SELECT IF(1,NULL,7) FROM t WHERE TRUE),7 IN(SELECT NULL FROM t WHERE FALSE),7 IN(SELECT NULL FROM t WHERE @v)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, '0', '0']], $result->rows);
    }

}
