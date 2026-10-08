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
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;

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

    public function testOrderedAnswersTheRowsInTheOrderOfAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5), KEY ib (b)); INSERT INTO t VALUES (1,'b'),(2,'a'),(3,NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([3, 2, 1], (new HandlerCommand())->ordered($table->data->rows, $table->definition->keys[1], $table));
    }

    public function testCompareComparesTheLeadingColumnsOfAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, KEY i (a, b))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([0, 1, -1], [(new HandlerCommand())->compare([1, 5], $table->definition->keys[0], $table, [1]), (new HandlerCommand())->compare([1, 5], $table->definition->keys[0], $table, [1, 4]), (new HandlerCommand())->compare([1, 5], $table->definition->keys[0], $table, [2])]);
    }

    public function testStartStartsAReadOfAnotherOrderAfresh(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $statement = $session->analyze('HANDLER h READ NEXT')->statement;
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertInstanceOf(HandlerScan::class, $statement);

        self::assertSame([0, 1], (new HandlerCommand())->start($statement, [1, 2], [], null, $table, [], null));
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
