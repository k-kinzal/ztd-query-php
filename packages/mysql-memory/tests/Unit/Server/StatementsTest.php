<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use ArrayObject;
use MySqlMemory\Instance;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Server\Client;
use MySqlMemory\Server\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Statements::class)]
#[Small]
final class StatementsTest extends TestCase
{
    public function testHandlePreparesAStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $answered = $client->statements->handle(0x16, new PayloadReader('SELECT 1 AS one'));

        self::assertTrue($answered);
        self::assertSame(
            [
                "\x00\x01\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00",
                "\x03def\x00\x00\x00\x03one\x00\x0C\x3F\x00\x02\x00\x00\x00\x08\x81\x80\x00\x00\x00",
                "\xFE\x00\x00\x02\x00",
            ],
            array_slice($sent->getArrayCopy(), 1),
        );
    }

    public function testHandleClosesAStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->statements->prepare('SELECT 1');
        $closed = $client->statements->handle(0x19, new PayloadReader("\x01\x00\x00\x00"));
        $count = $sent->count();
        $client->statements->handle(0x17, new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00"));

        self::assertTrue($closed);
        self::assertSame(2, $client->session()->variables->read('statement_id'));
        self::assertSame(4, $count);
        self::assertSame(["\xFF\xDB\x04#HY000Unknown prepared statement handler (1) given to mysqld_stmt_execute"], array_slice($sent->getArrayCopy(), 4));
    }

    public function testHandleAnswersOkToAReset(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->statements->prepare('SELECT 1');
        $client->statements->handle(0x1A, new PayloadReader("\x01\x00\x00\x00"));

        self::assertSame(["\x00\x00\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), 4));
    }

    public function testPrepareSendsTheParameterAndColumnDefinitions(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->statements->prepare('SELECT 1 AS one FROM DUAL WHERE 1 = ?');

        self::assertSame(6, $sent->count());
        self::assertSame(["\x00\x01\x00\x00\x00\x01\x00\x01\x00\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1, 1));
        self::assertSame(
            ["\xFE\x00\x00\x02\x00", "\x03def\x00\x00\x00\x03one\x00\x0C\x3F\x00\x02\x00\x00\x00\x08\x81\x80\x00\x00\x00", "\xFE\x00\x00\x02\x00"],
            array_slice($sent->getArrayCopy(), 3),
        );
    }

    public function testPrepareNumbersTheStatementsFromOne(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->statements->prepare('DO 1');
        $client->statements->prepare('DO 2');

        self::assertSame(["\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00", "\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1));
        self::assertSame(2, $client->session()->variables->read('statement_id'));
    }

    public function testPrepareAnswersTheErrorOfAStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $answered = $client->statements->prepare('SELECT * FROM nowhere');

        self::assertTrue($answered);
        self::assertSame(["\xFF\x16\x04#3D000No database selected"], array_slice($sent->getArrayCopy(), 1));
    }

    public function testColumnsAnswersTheColumnsOfAQuery(): void
    {
        $client = new Client(new Instance(), 1, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();

        self::assertEquals([new ResultColumn('one', Field::LongLong, 2, 0, 32897, 63)], $client->statements->columns($session->analyze('SELECT 1 AS one', true), $session, 0));
    }

    public function testColumnsAnswersNoColumnsForAStatementWithoutRows(): void
    {
        $client = new Client(new Instance(), 1, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();

        self::assertSame([], $client->statements->columns($session->analyze('DO 1', true), $session, 0));
    }

    public function testExecuteRunsTheStatementWithItsParameters(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query("CREATE TABLE d.t (id INT, name VARCHAR(20)); INSERT INTO d.t VALUES (7, 'ink'), (9, 'pen')");
        $sent = new ArrayObject();
        $client = new Client($instance, 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00d\x00");
        $client->statements->prepare('SELECT name FROM t WHERE id = ?');
        $before = $sent->count();
        $answered = $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x01\x08\x00\x07\x00\x00\x00\x00\x00\x00\x00"));
        $replies = array_slice($sent->getArrayCopy(), $before);

        self::assertTrue($answered);
        self::assertCount(5, $replies);
        self::assertSame(["\x01"], array_slice($replies, 0, 1));
        self::assertSame(["\xFE\x00\x00\x02\x00", "\x00\x00\x03ink", "\xFE\x00\x00\x02\x00"], array_slice($replies, 2));
    }

    public function testExecuteReusesTheTypesLastBound(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query("CREATE TABLE d.t (id INT, name VARCHAR(20)); INSERT INTO d.t VALUES (7, 'ink'), (9, 'pen')");
        $sent = new ArrayObject();
        $client = new Client($instance, 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00d\x00");
        $client->statements->prepare('SELECT name FROM t WHERE id = ?');
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x01\x08\x00\x07\x00\x00\x00\x00\x00\x00\x00"));
        $before = $sent->count();
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x09\x00\x00\x00\x00\x00\x00\x00"));

        self::assertSame(["\x00\x00\x03pen", "\xFE\x00\x00\x02\x00"], array_slice($sent->getArrayCopy(), $before + 3));
    }

    public function testExecuteAnswersOkForAWrite(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (id INT PRIMARY KEY, name VARCHAR(20))');
        $sent = new ArrayObject();
        $client = new Client($instance, 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00d\x00");
        $client->statements->prepare('INSERT INTO t VALUES (?, ?)');
        $before = $sent->count();
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x01\x08\x00\xFD\x00\x07\x00\x00\x00\x00\x00\x00\x00\x03ink"));
        $result = $instance->connect()->query('SELECT id, name FROM d.t')[0];

        self::assertSame(["\x00\x01\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), $before));
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', 'ink']], $result->rows);
    }

    public function testExecuteAnswersTheErrorOfTheStatement(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query("CREATE TABLE d.t (id INT PRIMARY KEY, name VARCHAR(20)); INSERT INTO d.t VALUES (7, 'ink')");
        $sent = new ArrayObject();
        $client = new Client($instance, 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00d\x00");
        $client->statements->prepare('INSERT INTO t VALUES (?, ?)');
        $before = $sent->count();
        $answered = $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x01\x08\x00\xFD\x00\x07\x00\x00\x00\x00\x00\x00\x00\x03pen"));

        self::assertTrue($answered);
        self::assertSame(["\xFF\x26\x04#23000Duplicate entry '7' for key 't.PRIMARY'"], array_slice($sent->getArrayCopy(), $before));
    }

    public function testExecuteAnswersAnUnknownStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $answered = $client->statements->execute(new PayloadReader("\x09\x00\x00\x00\x00\x01\x00\x00\x00"));

        self::assertTrue($answered);
        self::assertSame(["\xFF\xDB\x04#HY000Unknown prepared statement handler (9) given to mysqld_stmt_execute"], array_slice($sent->getArrayCopy(), 1));
    }

    public function testLongDataReplacesTheValueOfAParameter(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (id INT PRIMARY KEY, name TEXT)');
        $sent = new ArrayObject();
        $client = new Client($instance, 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00d\x00");
        $client->statements->prepare('INSERT INTO t VALUES (?, ?)');
        $before = $sent->count();
        $first = $client->statements->longData(new PayloadReader("\x01\x00\x00\x00\x01\x00in"));
        $second = $client->statements->longData(new PayloadReader("\x01\x00\x00\x00\x01\x00k"));
        $silent = $sent->count();
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x01\x08\x00\xFC\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00"));
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x09\x00\x00\x00\x00\x00\x00\x00\x03pen"));
        $result = $instance->connect()->query('SELECT id, name FROM d.t ORDER BY id')[0];

        self::assertTrue($first);
        self::assertTrue($second);
        self::assertSame($before, $silent);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['8', 'ink'], ['9', 'pen']], $result->rows);
    }

    public function testLongDataIgnoresAnUnknownStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        self::assertTrue($client->statements->longData(new PayloadReader("\x05\x00\x00\x00\x00\x00data")));
        self::assertSame(1, $sent->count());
    }

    public function testCloseForgetsTheStatement(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->statements->prepare('DO 1');
        $closed = $client->statements->close(1);
        $client->statements->execute(new PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00"));

        self::assertTrue($closed);
        self::assertSame(["\xFF\xDB\x04#HY000Unknown prepared statement handler (1) given to mysqld_stmt_execute"], array_slice($sent->getArrayCopy(), 2));
    }

    public function testCloseOfAnUnknownStatementAnswersTrue(): void
    {
        $client = new Client(new Instance(), 1, static function (string $bytes): void {
        });

        self::assertTrue($client->statements->close(42));
        self::assertSame($client, $client->statements->client);
    }
}
