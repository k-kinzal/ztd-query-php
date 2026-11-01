<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Window\CopiedAssignments;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CopiedAssignments::class)]
#[Small]
final class CopiedAssignmentsTest extends TestCase
{
    public function testCopiesPublishesTheStoredCharsetOnlyForBareAssignmentFields(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT @x:=FIRST_VALUE(d) OVER (),CONCAT(@y:=FIRST_VALUE(d) OVER ()) FROM t');
        $state = $session->query('SELECT @x,CHARSET(@x),@y,CHARSET(@y)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4', '2001-01-02', 'latin1']], $state->rows);
    }

    public function testCopiesKeepsSkippedRowsAtTheirInitialAssignmentCharset(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT @x:=FIRST_VALUE(d) OVER () FROM t LIMIT 1 OFFSET 3');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'latin1']], $state->rows);
    }

    public function testBufferedFinishesDuplicateInputEffectsBeforePublishingTheStoredField(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT DISTINCT @x:=FIRST_VALUE(d) OVER () FROM t');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }

    public function testSortedReadsTheStoredAssignmentEvenWhenOffsetDiscardsEveryRow(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT DISTINCT @x:=FIRST_VALUE(d) OVER () AS v FROM t ORDER BY v DESC LIMIT 1 OFFSET 3');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }

    public function testSortedKeepsTheAliasPositionWhenRollupRebuildsOutputFacts(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT DISTINCT @x:=FIRST_VALUE(d) OVER () AS v FROM t GROUP BY d WITH ROLLUP ORDER BY v DESC LIMIT 1 OFFSET 5');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }

    public function testSortedPublishesAssignmentsInKeyOrderBeforeDiscardingRows(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql; SET NAMES utf8mb4; CREATE TEMPORARY TABLE t(d DATE); INSERT INTO t VALUES('2001-01-02'),('2001-01-03')");
        $session->query('SELECT DISTINCT @x:=FIRST_VALUE(d) OVER () AS a,@x:=ROW_NUMBER() OVER () AS b FROM t ORDER BY b,a LIMIT 1 OFFSET 5');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }
    public function testCopiesPublishesAnAggregateAssignmentStoredBesideAWindow(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;CREATE TABLE t(n INT,d DATE);INSERT INTO t VALUES(1,'2001-01-02'),(2,'2001-01-03');SET @x=0");
        $session->query('SELECT DISTINCT @x:=MIN(d),ROW_NUMBER() OVER () FROM t GROUP BY 1+1 WITH ROLLUP');
        $state = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $state);
        self::assertSame([['2001-01-02', 'utf8mb4']], $state->rows);
    }

}
