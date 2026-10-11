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

    public function testSourceReadsBufferedColumnsWithoutChangingTheQueryInput(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY,n INT); INSERT INTO t VALUES (1,10),(2,NULL)');
        $result = $session->query('SELECT (SELECT n+1,NULL FROM t WHERE 0) <=> (NULL,NULL), (SELECT id,n FROM t WHERE id=99) <=> (NULL,NULL), (SELECT id,NULL FROM t WHERE 0) <=> (NULL,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0']], $result->rows);
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

    public function testReadableKeepsOuterRowsAvailableAfterAnEmptyInnerResult(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY, a INT); INSERT INTO t VALUES(1,NULL),(2,10)');
        $result = $session->query('SELECT id, (SELECT t.a,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS empty_null FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['2', '0']], $result->rows);
    }

    public function testReadableKeepsOuterAliasesAvailableAfterAnEmptyInnerResult(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY, a INT); INSERT INTO t VALUES(1,NULL),(2,10)');
        $result = $session->query('SELECT a+1 AS v, (SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS empty_null FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1'], ['11', '0']], $result->rows);
    }

    public function testReadableKeepsProgramVariablesAvailableWithoutAnInputRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE PROCEDURE p(IN v INT) SELECT (SELECT v,NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n');
        $result = $session->query('CALL p(1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testReadableKeepsNestedQueriesLazyAndPreservesTheirWarnings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (SELECT (SELECT 1/0 FROM DUAL WHERE 1),NULL FROM DUAL WHERE 0) <=> (NULL,NULL), (SELECT NULL,(SELECT 1/0 FROM DUAL WHERE 1) FROM DUAL WHERE 0) <=> (1,NULL)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1365', 'Division by 0']], $warnings->rows);
    }

    public function testInitialKeepsCountZeroDistinctFromUnknownExtrema(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT NULLIF(COUNT(*),0),MIN(1) FROM DUAL HAVING 0) <=> (NULL,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testOfReadsUninitializedSetFieldsWithoutEvaluatingTheirExpressions(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (SELECT 1/0,NULL FROM DUAL WHERE 0 UNION ALL SELECT 1/0,NULL FROM DUAL WHERE 0) <=> (NULL,NULL)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testOfRetainsTheLastSortedSetRowDiscardedByOffset(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT NULL a,NULL b UNION ALL SELECT 1,NULL ORDER BY a DESC LIMIT 1 OFFSET 2) <=> (NULL,NULL), (SELECT NULL a,NULL b UNION ALL SELECT 1,NULL ORDER BY a ASC LIMIT 1 OFFSET 2) <=> (NULL,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }
}
