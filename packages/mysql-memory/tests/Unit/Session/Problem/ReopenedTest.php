<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Reopened;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reopened::class)]
#[Small]
final class ReopenedTest extends TestCase
{
    public function testCheckRefusesATemporaryTableNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE x1 (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1137);
        $this->expectExceptionMessage("Can't reopen table: 'a'");

        $session->query('SELECT * FROM x1 AS a JOIN x1 AS b');
    }

    public function testTemporaryAnswersTheTemporaryTableAReferenceNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE x2 (a INT); CREATE TEMPORARY TABLE x1 (a INT)');

        $result1 = $session->query('SELECT count(*) FROM x2 a JOIN x2 b')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['0']], $result1->rows);
    }
    public function testReadsVisitsOuterFromBeforeProjectionSubqueries(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE x1 (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1137);
        $this->expectExceptionMessage("Can't reopen table: 'outer_table'");

        $session->query('SELECT (SELECT SUM(a) FROM x1 inner_table) FROM x1 outer_table');
    }

    public function testReadsRespectsACommonTableShadowingATemporaryTable(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('USE mysql;CREATE TEMPORARY TABLE x1(a INT);INSERT INTO x1 VALUES(1)');
        $result = $session->query('WITH x1 AS(SELECT 7 a) SELECT x.a,y.a FROM mysql.x1 x JOIN x1 y')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '7']], $result->rows);
    }

    public function testReadsExpandsEveryReferenceToAMaterializedCommonTable(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('USE mysql;CREATE TEMPORARY TABLE x1(a INT);INSERT INTO x1 VALUES(1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1137);
        $this->expectExceptionMessage("Can't reopen table: 'inner_table'");

        $session->query('WITH c AS(SELECT a FROM x1 inner_table LIMIT 1) SELECT * FROM c x JOIN c y');
    }

    public function testReadsLeavesUnusedDefinitionsUnopened(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('USE mysql;CREATE TEMPORARY TABLE x1(a INT);INSERT INTO x1 VALUES(1)');
        $result = $session->query('WITH c AS(SELECT a FROM x1 inner_table) SELECT * FROM x1 outer_table')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testReadsStopsAtARecursiveSelfReference(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('USE mysql;CREATE TEMPORARY TABLE x1(a INT);INSERT INTO x1 VALUES(1)');
        $result = $session->query('WITH RECURSIVE c(n) AS(SELECT a FROM x1 UNION ALL SELECT n+1 FROM c WHERE n<3) SELECT n FROM c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2'], ['3']], $result->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerOpeningProblems(): iterable
    {
        yield 'column' => ['SELECT unknown_column FROM x1 first_table JOIN x1 second_table'];
        yield 'routine' => ['SELECT unknown_function(1) FROM x1 first_table JOIN x1 second_table'];
        yield 'missing table' => ['SELECT * FROM no_such_table JOIN x1 first_table JOIN x1 second_table'];
    }

    #[DataProvider('providerOpeningProblems')]
    public function testCheckPrecedesOtherTableAndNameErrors(string $sql): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('USE mysql;CREATE TEMPORARY TABLE x1(a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1137);
        $this->expectExceptionMessage("Can't reopen table: 'first_table'");

        $session->query($sql);
    }

}
