<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Subquery\RowComparison;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

#[CoversClass(RowComparison::class)]
#[Small]
final class RowComparisonTest extends TestCase
{
    public function testDomainAnswersTheResolvedTruthDomain(): void
    {
        $domain = Domain::integer();
        $comparison = new RowComparison(ComparisonOperator::Equal, [], [], [], $domain);

        self::assertSame($domain, $comparison->domain());
    }

    public function testEvaluateComparesAllColumnsWithThreeValuedEqualityAndOrdering(): void
    {
        $result = (new Instance())->connect()->query('SELECT (1,2) = (SELECT 1,2), (1,2) < (SELECT 1,3), (NULL,1) = (SELECT NULL,2), (1,NULL) = (SELECT 1,2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', null]], $result->rows);
    }

    public function testValuesReadBothSubqueriesAndPreserveEmptyResults(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT 1,2) = (SELECT 1,2), (SELECT 1,2 WHERE 0) <=> (SELECT 1,2 WHERE 0), (1,2) = (SELECT 1,2 WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null]], $result->rows);
    }

    public function testEvaluateTreatsTwoEmptyRowSubqueriesAsNullSafeEqualInMySql57(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query('SELECT (SELECT 1,2 WHERE 0) <=> (SELECT 1,2 WHERE 0), (NULL,NULL) <=> (SELECT 1,2 WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }
}
