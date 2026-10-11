<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\EmptyProjection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyProjection::class)]
#[Small]
final class EmptyProjectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacy(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51'];
        yield 'MySQL 5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacy')]
    public function testRetainClearsTheFirstRuntimeProjectionWithoutRepeatingItsAssignment(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query('SET @v=0');
        $result = $session->query('SELECT (SELECT @v:=@v+1,NULL FROM DUAL WHERE 0) <=> (1,NULL),@v FROM (SELECT 1 UNION ALL SELECT 2) t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['0', '1']], $result->rows);
    }

    #[DataProvider('providerLegacy')]
    public function testRetainKeepsALaterRuntimeProjectionWithoutRepeatingItsAssignment(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query('SET @v=0');
        $result = $session->query('SELECT (SELECT NULL,@v:=@v+1 FROM DUAL WHERE 0) <=> (NULL,1),@v FROM (SELECT 1 UNION ALL SELECT 2) t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['1', '1']], $result->rows);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerWrappedVariables(): iterable
    {
        foreach (['@v', '@v:=@v+1'] as $expression) {
            foreach (['(' . $expression . ')', '+(' . $expression . ')', '{fn (' . $expression . ')}'] as $wrapped) {
                yield $wrapped => [$expression, $wrapped];
            }
        }
    }

    #[DataProvider('providerWrappedVariables')]
    public function testRetainUsesTheResolvedExpressionThroughTransparentWrappers(string $expression, string $wrapped): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('SET @v=1');
        $direct = $session->query('SELECT (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (NULL,NULL),@v FROM (SELECT 1 UNION ALL SELECT 2) t')[0];
        $session->query('SET @v=1');
        $result = $session->query('SELECT (SELECT ' . $wrapped . ',NULL FROM DUAL WHERE 0)<=> (NULL,NULL),@v FROM (SELECT 1 UNION ALL SELECT 2) t')[0];

        self::assertInstanceOf(ResultSet::class, $direct);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($direct->rows, $result->rows);
    }

    /**
     * @return iterable<string, array{string, list<list<string|null>>}>
     */
    public static function providerComputedSources(): iterable
    {
        yield 'first source is nonnull' => ['(1,10),(2,NULL),(3,20)', [['1', '11', '0', '0', '0'], ['2', null, '1', '0', '0'], ['3', '21', '1', '0', '0']]];
        yield 'first source is null' => ['(1,NULL),(2,10),(3,20)', [['1', null, '1', '1', '1'], ['2', '11', '1', '1', '1'], ['3', '21', '1', '1', '1']]];
    }

    /**
     * @param list<list<string|null>> $expected
     */
    #[DataProvider('providerComputedSources')]
    public function testRetainDistinguishesComputedSourcesFromDirectSlotsOnMySql56(string $values, array $expected): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY,a INT); INSERT INTO t VALUES ' . $values);
        $result = $session->query('SELECT id,a+1 AS v,(SELECT t.a,NULL FROM DUAL WHERE 0) <=> (NULL,NULL),(SELECT t.a+1,NULL FROM DUAL WHERE 0) <=> (NULL,NULL),(SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($expected, $result->rows);
    }

    public function testOfKeepsNullProducingExpressionsReadableWithoutAnInputRow(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT SQRT(-1),CONCAT(NULL) FROM DUAL WHERE 0) <=> (NULL,NULL), (SELECT 1,NULL LIMIT 0) <=> (2,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testOfPreservesComputedWindowOutput(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT ROW_NUMBER() OVER (),1) <=> (1,1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testOfReadsTheInitialStateOfAnUnreadRankWindow(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT NULLIF(ROW_NUMBER() OVER (),1),NULLIF(PERCENT_RANK() OVER (),0) FROM DUAL WHERE 0) <=> (NULL,NULL)')[0];

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

    public function testReadableKeepsColumnDefaultsWithoutReadingATableRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7,b INT DEFAULT NULL)');
        $result = $session->query('SELECT (SELECT DEFAULT(a),NULL FROM t WHERE 0) <=> (0,NULL), (SELECT DEFAULT(b),NULL FROM t WHERE 0) <=> (NULL,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
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
