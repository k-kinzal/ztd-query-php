<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Aggregate\TupleInput;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TupleInput::class)]
#[Small]
final class TupleInputTest extends TestCase
{
    public function testEnabledRejectsKnownNullBeforeReadingOtherArguments(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT COUNT(DISTINCT @x:=@x+1,CAST(NULL AS SIGNED)) FROM t GROUP BY 1+1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertSame(0, $session->variables->user('x')[0]);
    }

    public function testCaptureReadsSortKeysAndCompleteGroupedTuplesBeforeNullRejection(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT GROUP_CONCAT(IF(n=1,NULL,n),@x:=@x+1 ORDER BY @x:=@x+10) FROM t GROUP BY 1+1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['211,233']], $result->rows);
        self::assertSame(33, $session->variables->user('x')[0]);
    }

    public function testCompleteReadsImplicitConstantArgumentsAfterSortingAcceptedRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT GROUP_CONCAT(@x:=@x+1,IF(n=1,NULL,n) ORDER BY n) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12,22']], $result->rows);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    public function testCompleteKeepsDeferredNullTextEmptyWithoutNumericConversion(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT GROUP_CONCAT(@x:=NULLIF(@x,0),n) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2,1,2']], $result->rows);
        self::assertNull($session->variables->user('x')[0]);
    }
}
