<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Subquery\RowMembership;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowMembership::class)]
#[Small]
final class RowMembershipTest extends TestCase
{
    public function testDomainAnswersTheResolvedTruthType(): void
    {
        $domain = Domain::integer();
        $membership = new RowMembership([], new Rows(new QueryPlan(new ZeroRows(0), [], [])), [], false, $domain);

        self::assertSame($domain, $membership->domain());
    }

    public function testEvaluateNegatesEmptySubqueriesEvenWithNullOperands(): void
    {
        $result = (new Instance())->connect()->query('SELECT (NULL, NULL) IN (SELECT 1, 2 WHERE 0), (NULL, NULL) NOT IN (SELECT 1, 2 WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
    }

    public function testMatchesKeepsUnknownOnlyWhenNoColumnPairIsUnequal(): void
    {
        $result = (new Instance())->connect()->query('SELECT (1, NULL) IN (SELECT 1, 2), (1, NULL) IN (SELECT 2, 2), (1, 2) IN (SELECT 1, NULL UNION ALL SELECT 1, 2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0', '1']], $result->rows);
    }

    public function testReadReadsCorrelatedRowsForEachOuterRow(): void
    {
        $result = (new Instance())->connect()->query('SELECT id, (id, v) IN (SELECT 1, 10 WHERE id=1) FROM (SELECT 1 id, 10 v UNION ALL SELECT 2, 20) t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['2', '0']], $result->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => ['5.6.51'];
        yield '5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacyReleases')]
    public function testReadEvaluatesOnlySatisfiedLegacyConstantConditionsAgain(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20))');
        $session->query("INSERT INTO t VALUES (1,'apple')");
        $session->query('SELECT (0,1) IN (SELECT s,id FROM t)');
        $matching = $session->query('SHOW WARNINGS')[0];
        $session->query('SELECT (1,1) IN (SELECT s,id FROM t)');
        $unequal = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $matching);
        self::assertCount(2, $matching->rows);
        self::assertInstanceOf(ResultSet::class, $unequal);
        self::assertCount(1, $unequal->rows);
    }

    public function testMatchesFallsBackToScanningForANullKeyAndStopsAtTheFirstUnknownRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, part INT, s VARCHAR(20), PRIMARY KEY(id,part))');
        $session->query("INSERT INTO t VALUES (1,1,'a'),(2,1,'b')");
        $session->query('SELECT (NULL,0,2) IN (SELECT part,s,id FROM t)');
        $partial = $session->query('SHOW WARNINGS')[0];
        $session->query('SELECT (1,0,NULL) IN (SELECT part,s,id FROM t)');
        $first = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $partial);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $partial->rows);
        self::assertInstanceOf(ResultSet::class, $first);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $first->rows);
    }
}
