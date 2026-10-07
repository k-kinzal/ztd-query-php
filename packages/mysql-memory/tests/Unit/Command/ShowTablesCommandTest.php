<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\ShowTablesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowTablesCommand::class)]
#[Small]
final class ShowTablesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowTablesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheTablesInNameOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE b (a INT); CREATE TABLE a_x (a INT); CREATE TABLE ab (a INT)');

        $result = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['a_x'], ['ab'], ['b']], $result->rows);
        self::assertSame(['Tables_in_d', 'TABLE_NAME', 'TABLES', 'information_schema'], [$result->columns[0]->name, $result->columns[0]->originalName, $result->columns[0]->originalTable, $result->columns[0]->schema]);
    }

    public function testExecuteListsTheTablesOfTheDatabaseItNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE e; USE e; CREATE TABLE t (a INT); USE d');

        $result = $session->query('SHOW TABLES FROM e')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['t']], $result->rows);
        self::assertSame('Tables_in_e', $result->columns[0]->name);
    }

    public function testExecuteFiltersTheTablesByLike(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE b (a INT); CREATE TABLE a_x (a INT); CREATE TABLE ab (a INT)');

        $one = $session->query("SHOW TABLES LIKE 'a_'")[0];
        $any = $session->query("SHOW TABLES LIKE 'a%'")[0];

        self::assertInstanceOf(ResultSet::class, $one);
        self::assertSame([['ab']], $one->rows);
        self::assertInstanceOf(ResultSet::class, $any);
        self::assertSame([['a_x'], ['ab']], $any->rows);
    }

    public function testExecuteRefusesWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('SHOW TABLES');
    }

    public function testExecuteRefusesAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        $session->query('SHOW TABLES FROM nope');
    }
}
