<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\LogfileGroupCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogfileGroupCommand::class)]
#[Small]
final class LogfileGroupCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new LogfileGroupCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesLogfileGroupsUnderInnoDb(): void
    {
        $session = (new Instance())->connect();
        $session->run("ALTER LOGFILE GROUP g ADD UNDOFILE 'u' INITIAL_SIZE 1 WAIT");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '3658', 'Feature LOGFILE GROUP is unsupported (by InnoDB).'], ['Error', '1178', "The storage engine for the table doesn't support CREATE/ALTER/DROP LOGFILE GROUP"]], $warnings->rows);
    }

    public function testExecuteChecksTheEngineFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $this->expectExceptionMessage("Unknown storage engine 'ndbcluster'");

        $session->query('DROP LOGFILE GROUP g ENGINE=ndbcluster');
    }

    public function testExecuteRefusesARepeatedEngine(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1527);
        $this->expectExceptionMessage('It is not allowed to specify STORAGE ENGINE more than once');

        $session->query("DROP LOGFILE GROUP g ENGINE = 'text', ENGINE = x");
    }
}
