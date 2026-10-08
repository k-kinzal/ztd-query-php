<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ShowRoutinesCommand;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowRoutinesCommand::class)]
#[Small]
final class ShowRoutinesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowRoutinesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheRoutinesOfTheKindByDatabaseAndName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p2() COMMENT 'two' SELECT 1");
        $session->query("CREATE DEFINER = 'bob'@'host' PROCEDURE P1() SQL SECURITY INVOKER SELECT 1");
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1');

        $result1 = $session->query("SHOW PROCEDURE STATUS WHERE Db = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $rows = $result1->rows;

        self::assertSame([['d', 'P1', 'PROCEDURE', 'SQL', 'bob@host', 'INVOKER', ''], ['d', 'p2', 'PROCEDURE', 'SQL', 'root@%', 'DEFINER', 'two']], array_map(static fn (array $row): array => [$row[0], $row[1], $row[2], $row[3], $row[4], $row[7], $row[8]], $rows));
    }

    public function testExecuteMatchesLikeAgainstTheName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f1() RETURNS INT DETERMINISTIC RETURN 1');
        $session->query('CREATE FUNCTION g() RETURNS INT DETERMINISTIC RETURN 1');

        $result2 = $session->query("SHOW FUNCTION STATUS LIKE 'f%'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        $rows = $result2->rows;

        self::assertSame(['f1'], array_column($rows, 1));
    }

    public function testHeadingsDescribeTheColumnsOfTheRoutinesTable(): void
    {
        $headings = (new ShowRoutinesCommand())->headings();

        self::assertSame(['Db', 'Name', 'Type', 'Language', 'Definer', 'Modified', 'Created', 'Security_type', 'Comment', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (Heading $heading): string => $heading->name, $headings));
        self::assertSame(['ROUTINES', 'schemata', 4225], [$headings[0]->table, $headings[0]->originalTable, $headings[0]->flags]);
    }
}
