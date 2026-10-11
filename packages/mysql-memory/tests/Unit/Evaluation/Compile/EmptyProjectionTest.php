<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\EmptyProjection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyProjection::class)]
#[Small]
final class EmptyProjectionTest extends TestCase
{
    public function testOfKeepsNullProducingExpressionsReadableWithoutAnInputRow(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT SQRT(-1),CONCAT(NULL) FROM DUAL WHERE 0) <=> (NULL,NULL), (SELECT 1,NULL LIMIT 0) <=> (2,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testOfDoesNotTreatWindowOutputAsAnIndependentExpression(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT ROW_NUMBER() OVER (),1) <=> (1,1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testProjectFindsExpressionsBelowOrderingAndLimit(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT 1,NULL ORDER BY 1 LIMIT 0) <=> (2,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testAggregatePreservesCountAndExtremaBeforeHavingReadsAnyInput(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT COUNT(*),MAX(1) FROM DUAL HAVING 0) <=> (99,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testReadableDoesNotEvaluateTheArgumentsOfAnUnreadAggregate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (SELECT SUM(1/0),MIN(1/0) FROM DUAL HAVING 0) <=> (NULL,NULL)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testInitialKeepsCountZeroDistinctFromUnknownExtrema(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT NULLIF(COUNT(*),0),MIN(1) FROM DUAL HAVING 0) <=> (NULL,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }
}
