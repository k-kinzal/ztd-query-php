<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\ShowColumnsCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowColumnsCommand::class)]
#[Small]
final class ShowColumnsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowColumnsCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheColumnsOfATable(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10) DEFAULT 'x', c DECIMAL(5,2) UNSIGNED NOT NULL, KEY kb (b(3), c DESC))");

        $result = $session->query('DESCRIBE t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['a', 'int', 'NO', 'PRI', null, ''], ['b', 'varchar(10)', 'YES', 'MUL', 'x', ''], ['c', 'decimal(5,2) unsigned', 'NO', '', null, '']], $result->rows);
        self::assertSame([67108860, 'COLUMNS', 'columns'], [$result->columns[1]->length, $result->columns[1]->table, $result->columns[1]->originalTable]);
    }

    public function testExecuteAddsTheCollationPrivilegesAndCommentWhenFull(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(10) COMMENT 'hi')");

        $result = $session->query('SHOW FULL COLUMNS FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['a', 'int', null, 'YES', '', null, '', 'select,insert,update,references', ''], ['b', 'varchar(10)', 'utf8mb4_0900_ai_ci', 'YES', '', null, '', 'select,insert,update,references', 'hi']], $result->rows);
    }

    public function testExecuteMatchesDescribeAPatternWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, ba INT, bb INT)');

        $result = $session->query("DESC t 'B%'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['ba', 'bb'], array_column($result->rows, 0));
    }

    public function testExecuteRefusesAnUnknownDatabaseAfterFrom(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('SHOW COLUMNS FROM t FROM nodb');
    }

    public function testExecuteRefusesAnUnknownColumnOfTheWhereClause(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'nocol' in 'where clause'");

        $session->query('SHOW COLUMNS FROM t WHERE nocol = 1');
    }

    public function testHeadingsAddTheColumnsOfFull(): void
    {
        self::assertSame(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], array_map(static fn (Heading $heading): string => $heading->name, (new ShowColumnsCommand())->headings(false)));
        self::assertSame(['Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges', 'Comment'], array_map(static fn (Heading $heading): string => $heading->name, (new ShowColumnsCommand())->headings(true)));
    }
}
