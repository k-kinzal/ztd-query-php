<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ShowEventsCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowEventsCommand::class)]
#[Small]
final class ShowEventsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowEventsCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheEventsByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE EVENT e3 ON SCHEDULE EVERY '1:30' HOUR_MINUTE STARTS '2030-01-01' ENDS '2031-01-01' DO SELECT 1");
        $session->query("CREATE EVENT e2 ON SCHEDULE AT '2030-01-01 00:00:00' DISABLE ON REPLICA DO SELECT 2");

        $result1 = $session->query('SHOW EVENTS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $rows = $result1->rows;

        self::assertSame([
            ['d', 'e2', 'root@%', 'SYSTEM', 'ONE TIME', '2030-01-01 00:00:00', null, null, null, null, 'REPLICA_SIDE_DISABLED', '1'],
            ['d', 'e3', 'root@%', 'SYSTEM', 'RECURRING', null, "'1:30'", 'HOUR_MINUTE', '2030-01-01 00:00:00', '2031-01-01 00:00:00', 'ENABLED', '1'],
        ], array_map(static fn (array $row): array => array_slice($row, 0, 12), $rows));
    }

    public function testExecuteAnswersNoEventOfAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $result2 = $session->query("SHOW EVENTS FROM nodb LIKE 'x'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([], $result2->rows);
    }

    public function testHeadingsDescribeAConstantTableOtherwise(): void
    {
        $command = new ShowEventsCommand();

        self::assertSame([[0, 'events', ''], [31, 'evt', 'information_schema']], [[$command->headings(false)[4]->decimals, $command->headings(false)[1]->originalTable, $command->headings(false)[1]->schema], [$command->headings(true)[4]->decimals, $command->headings(true)[1]->originalTable, $command->headings(true)[1]->schema]]);
    }

    public function testExecuteSendsTheStatusAsTheEnumColumnOfTheEventsInMySql80(): void
    {
        $session = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1');
        $result = $session->query('SHOW EVENTS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['Status', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::String, 72, 0], [$result->columns[10]->name, $result->columns[10]->type, $result->columns[10]->length, $result->columns[10]->decimals]);
    }
}
