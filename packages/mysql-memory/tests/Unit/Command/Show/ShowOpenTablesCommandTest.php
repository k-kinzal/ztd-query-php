<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ShowOpenTablesCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowOpenTablesCommand::class)]
#[Small]
final class ShowOpenTablesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowOpenTablesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheTablesOfADatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('SHOW OPEN TABLES FROM d')[0];
        $missing = $session->query('SHOW OPEN TABLES FROM nodb')[0];
        $cased = $session->query("SHOW OPEN TABLES IN d LIKE 'T'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertInstanceOf(ResultSet::class, $cased);
        self::assertSame([['d', 't', '0', '0']], $result->rows);
        self::assertSame([], $missing->rows);
        self::assertSame([], $cased->rows);
        self::assertSame(['OPEN_TABLES', 'information_schema', 8], [$result->columns[2]->table, $result->columns[2]->schema, $result->columns[2]->type->value]);
    }
}
