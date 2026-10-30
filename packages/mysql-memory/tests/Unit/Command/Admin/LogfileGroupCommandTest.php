<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\LogfileGroupCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogfileGroupCommand::class)]
#[Small]
final class LogfileGroupCommandTest extends TestCase
{
    /**
     * @param non-empty-list<int> $codes
     */
    #[DataProvider('providerLockedCommands')]
    public function testExecuteChecksTableLocksInReleaseOrder(string $version, string $lock, string $sql, array $codes): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("SET sql_mode='NO_ENGINE_SUBSTITUTION'; CREATE TABLE t(id INT); LOCK TABLE t " . $lock . '; ROLLBACK');
        try {
            $session->query($sql);
            self::fail('The storage command must refuse the locked session.');
        } catch (SqlError $error) {
            self::assertSame($codes[0], $error->getCode());
        }
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
        self::assertCount(1, $session->locks);
        self::assertSame([], $session->instance->registry->tablespaces);
    }

    /**
     * @return iterable<string, array{string, string, string, non-empty-list<int>}>
     */
    public static function providerLockedCommands(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['READ', 'WRITE'] as $lock) {
                foreach ([
                    "CREATE LOGFILE GROUP lg ADD UNDOFILE 'undo'" => $version === '5.6.51' ? [1478] : ($version === '5.7.44' ? [1478, 1178] : [1192]),
                    "ALTER LOGFILE GROUP lg ADD UNDOFILE 'undo'" => [$version === '5.6.51' ? 1478 : ($version === '5.7.44' ? 1178 : 1192)],
                    'DROP LOGFILE GROUP lg' => [$version === '5.6.51' ? 1478 : ($version === '5.7.44' ? 1178 : 1192)],
                    "CREATE LOGFILE GROUP lg ADD UNDOFILE 'undo' ENGINE=unknown_engine" => [1286],
                    "CREATE LOGFILE GROUP lg ADD UNDOFILE 'undo' ENGINE=MyISAM" => [1478],
                ] as $sql => $codes) {
                    yield $version . ':' . $lock . ':' . $sql => [$version, $lock, $sql, $codes];
                }
            }
        }
    }

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
    public function testExecutePreservesTheMySql57UnsupportedOperationError(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run("ALTER LOGFILE GROUP g ADD UNDOFILE 'u'");

        self::assertSame([['Error', 1178, "The storage engine for the table doesn't support ALTER LOGFILE GROUP"]], $session->diagnostics->conditions);
    }

}
