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

    public function testEvaluateReadsCorrelatedRowsForEachOuterRow(): void
    {
        $result = (new Instance())->connect()->query('SELECT id, (id, v) IN (SELECT 1, 10 WHERE id=1) FROM (SELECT 1 id, 10 v UNION ALL SELECT 2, 20) t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['2', '0']], $result->rows);
    }
}
