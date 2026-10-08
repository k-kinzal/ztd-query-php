<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\Handler;
use MySqlMemory\Command\Access\HandlerCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;

#[CoversClass(HandlerCommand::class)]
#[Small]
final class HandlerCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new HandlerCommand())->clearsDiagnostics());
    }

    public function testExecuteReadsAnIndexFromWhereTheCursorStands(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5), c INT, KEY ib (b)); INSERT INTO t VALUES (3,'c',1),(1,'a',2),(2,'b',1),(4,NULL,2),(5,'b',NULL); HANDLER t OPEN AS h");

        $equal = $session->query("HANDLER h READ ib = ('b') LIMIT 5")[0];
        $next = $session->query('HANDLER h READ ib NEXT LIMIT 5')[0];
        $previous = $session->query('HANDLER h READ ib PREV LIMIT 2')[0];
        $below = $session->query("HANDLER h READ ib <= ('b') LIMIT 5")[0];

        self::assertInstanceOf(ResultSet::class, $equal);
        self::assertSame([['2', 'b', '1'], ['5', 'b', null]], $equal->rows);
        self::assertInstanceOf(ResultSet::class, $next);
        self::assertSame([['3', 'c', '1']], $next->rows);
        self::assertInstanceOf(ResultSet::class, $previous);
        self::assertSame([['3', 'c', '1'], ['5', 'b', null]], $previous->rows);
        self::assertInstanceOf(ResultSet::class, $below);
        self::assertSame([['5', 'b', null], ['2', 'b', '1'], ['1', 'a', '2'], ['4', null, '2']], $below->rows);
    }

    public function testExecuteScansTheTableWithAConditionAndAnOffset(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5)); INSERT INTO t VALUES (3,'c'),(1,'a'),(2,'b'),(4,NULL); HANDLER t OPEN");

        $first = $session->query('HANDLER t READ FIRST WHERE a > 1 LIMIT 1, 2')[0];
        $next = $session->query('HANDLER t READ NEXT LIMIT 10')[0];
        $end = $session->query('HANDLER t READ NEXT')[0];

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertSame([['3', 'c'], ['4', null]], $first->rows);
        self::assertSame([['a', 't', 't']], array_map(static fn ($column): array => [$column->name, $column->table, $column->originalTable], array_slice($first->columns, 0, 1)));
        self::assertInstanceOf(ResultSet::class, $next);
        self::assertSame([], $next->rows);
        self::assertInstanceOf(ResultSet::class, $end);
        self::assertSame([], $end->rows);
    }

    public function testExecuteReportsAnUnknownColumnOfTheConditionInTheFieldList(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); HANDLER t OPEN');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'zz' in 'field list'");

        $session->query('HANDLER t READ FIRST WHERE zz > 1');
    }

    public function testExecuteRefusesMoreValuesThanTheIndexHasParts(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY ia (a)); HANDLER t OPEN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1070);
        $this->expectExceptionMessage('Too many key parts specified; max 1 parts allowed');

        $session->query('HANDLER t READ ia = (1, 2)');
    }

    public function testExecuteClosesTheHandler(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); HANDLER t OPEN; HANDLER t CLOSE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1109);
        $this->expectExceptionMessage("Unknown table 't' in HANDLER");

        $session->query('HANDLER t CLOSE');
    }

    public function testOpenRefusesANameAnotherHandlerHas(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); HANDLER t OPEN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);

        $session->query('HANDLER t OPEN');
    }

    public function testOpenRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        $session->query('HANDLER abc.t OPEN');
    }

    public function testHandlerClosesTheHandlerOfADroppedTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); HANDLER t OPEN; DROP TABLE t');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1109);

        (new HandlerCommand())->handler($session, 't');
    }

    public function testKeyRefusesAnIndexTheTableLacks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Key 'zz' doesn't exist in table 'h'");

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        (new HandlerCommand())->key($table, 'zz', new Handler('d', 't', 'h'));
    }

    public function testConditionAnswersTheTextBetweenWhereAndLimit(): void
    {
        self::assertSame('a IN (SELECT 1 LIMIT 1)', (new HandlerCommand())->condition('HANDLER h READ FIRST WHERE a IN (SELECT 1 LIMIT 1) LIMIT 5', true));
    }

    public function testCloseClosesTheHandler(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); HANDLER t OPEN AS h');
        $statement = $session->analyze('HANDLER H CLOSE')->statement;
        self::assertInstanceOf(HandlerClose::class, $statement);

        (new HandlerCommand())->close($statement, $session);

        self::assertSame([], $session->handlers);
    }

    public function testReadMovesTheCursorPastTheEndWhenTheRowsRunOut(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY); INSERT INTO t VALUES (1),(2); HANDLER t OPEN; HANDLER t READ `PRIMARY` FIRST LIMIT 5');

        self::assertSame(['primary', true, 2], [$session->handlers['t']->order, $session->handlers['t']->placed, $session->handlers['t']->position]);
    }

    public function testSelectReadsEveryColumnOfTheTableUnderTheNameOfTheHandler(): void
    {
        self::assertSame(
            ['SELECT * FROM `d``x`.`t` AS `h` WHERE a > 1', 'SELECT * FROM `d``x`.`t` AS `h`'],
            [(new HandlerCommand())->select(new Handler('d`x', 't', 'h'), 'a > 1'), (new HandlerCommand())->select(new Handler('d`x', 't', 'h'), null)],
        );
    }

    public function testFilterSkipsTheRowsTheConditionRejects(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1),(2),(3),(NULL); HANDLER t OPEN');

        $result = $session->query('HANDLER t READ FIRST WHERE a <> 2 LIMIT 5')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['3']], $result->rows);
    }

    public function testValuesEvaluatesTheValuesOfAKeySeek(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY ia (a)); INSERT INTO t VALUES (1),(2),(3); HANDLER t OPEN');

        $result = $session->query('HANDLER t READ ia = (1 + 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testPlacePlacesTheCursorInAnOrder(): void
    {
        $handler = new Handler('d', 't', 'h');

        (new HandlerCommand())->place($handler, 'ia', 3);

        self::assertSame(['ia', true, 3], [$handler->order, $handler->placed, $handler->position]);
    }

    public function testResultAnswersTheVisibleColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT INVISIBLE); INSERT INTO t (a, b) VALUES (1, 2); HANDLER t OPEN');

        $result = $session->query('HANDLER t READ FIRST')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }
}
