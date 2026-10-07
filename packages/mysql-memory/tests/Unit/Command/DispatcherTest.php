<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\DatabaseCommand;
use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\DoCommand;
use MySqlMemory\Command\QueryCommand;
use MySqlMemory\Command\SetCommand;
use MySqlMemory\Command\ShowTablesCommand;
use MySqlMemory\Command\TransactionCommand;
use MySqlMemory\Command\WarningsCommand;
use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
#[Small]
final class DispatcherTest extends TestCase
{
    public function testCommandChoosesTheCommandOfEachKindOfQueryAndWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [QueryCommand::class, QueryCommand::class, InsertCommand::class, InsertCommand::class, InsertCommand::class, ChangeCommand::class, ChangeCommand::class, MultipleChangeCommand::class, MultipleChangeCommand::class],
            [
                $dispatcher->command($session->analyze('SELECT 1')->statement)::class,
                $dispatcher->command($session->analyze('VALUES ROW(1)')->statement)::class,
                $dispatcher->command($session->analyze('INSERT INTO t VALUES (1)')->statement)::class,
                $dispatcher->command($session->analyze('INSERT INTO t SET a = 1')->statement)::class,
                $dispatcher->command($session->analyze('INSERT INTO t SELECT 1')->statement)::class,
                $dispatcher->command($session->analyze('UPDATE t SET a = 1')->statement)::class,
                $dispatcher->command($session->analyze('DELETE FROM t')->statement)::class,
                $dispatcher->command($session->analyze('UPDATE t, u SET t.a = 1')->statement)::class,
                $dispatcher->command($session->analyze('DELETE t FROM t JOIN u')->statement)::class,
            ],
        );
    }

    public function testCommandChoosesTheCommandOfEachKindOfDefinitionAndUtility(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [CreateTableCommand::class, DropTableCommand::class, DropTableCommand::class, DatabaseCommand::class, DatabaseCommand::class, DatabaseCommand::class, SetCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, WarningsCommand::class, WarningsCommand::class, DoCommand::class, ShowTablesCommand::class],
            [
                $dispatcher->command($session->analyze('CREATE TABLE v (a INT)')->statement)::class,
                $dispatcher->command($session->analyze('DROP TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('TRUNCATE TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('CREATE DATABASE e')->statement)::class,
                $dispatcher->command($session->analyze('DROP DATABASE d')->statement)::class,
                $dispatcher->command($session->analyze('USE d')->statement)::class,
                $dispatcher->command($session->analyze('SET @a = 1')->statement)::class,
                $dispatcher->command($session->analyze('BEGIN')->statement)::class,
                $dispatcher->command($session->analyze('START TRANSACTION')->statement)::class,
                $dispatcher->command($session->analyze('COMMIT')->statement)::class,
                $dispatcher->command($session->analyze('ROLLBACK')->statement)::class,
                $dispatcher->command($session->analyze('SHOW WARNINGS')->statement)::class,
                $dispatcher->command($session->analyze('SHOW ERRORS')->statement)::class,
                $dispatcher->command($session->analyze('DO 1')->statement)::class,
                $dispatcher->command($session->analyze('SHOW TABLES')->statement)::class,
            ],
        );
    }

    public function testCommandRefusesAStatementNoCommandExecutes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $statement = $session->analyze('SHOW CREATE TABLE t')->statement;

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'ShowCreateTable'");

        (new Dispatcher())->command($statement);
    }
}
