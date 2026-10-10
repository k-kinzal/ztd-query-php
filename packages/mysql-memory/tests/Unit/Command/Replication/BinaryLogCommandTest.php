<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\BinaryLogCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryLogCommand::class)]
#[Small]
final class BinaryLogCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new BinaryLogCommand())->clearsDiagnostics());
    }

    public function testExecutePurgesTheFilesBeforeTheOneNamed(): void
    {
        $instance = new Instance();
        $instance->connect()->query("FLUSH BINARY LOGS; FLUSH BINARY LOGS; PURGE BINARY LOGS TO './binlog.000003'");

        self::assertSame([3], $instance->registry->binaryLog->files);
    }

    public function testExecuteRefusesAFileTheIndexLacks(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1373);
        $this->expectExceptionMessage('Target log not found in binlog index');

        $session->query("PURGE BINARY LOGS TO 'BINLOG.000001'");
    }

    public function testExecuteWarnsThatTheActiveFileIsNotPurged(): void
    {
        $session = (new Instance())->connect();
        $session->query("PURGE BINARY LOGS BEFORE 'garbage'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Incorrect datetime value: 'garbage'"], ['Warning', '1868', 'file ./binlog.000001 was not purged because it is the active log file.']], $warnings->rows);
    }

    public function testExecuteReadsTheIntegerZeroAsTheZeroDatetime(): void
    {
        $session = (new Instance())->connect();
        $session->query('PURGE BINARY LOGS BEFORE 0000000000000007941702 IS UNKNOWN');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1868', 'file ./binlog.000001 was not purged because it is the active log file.']], $warnings->rows);
    }

    public function testEventRefusesTextThatIsNoBase64(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1575);
        $this->expectExceptionMessage('Decoding of base64 string failed');

        (new BinaryLogCommand())->event("a'b");
    }

    public function testEventRefusesAShortEventAsASyntaxError(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1149);

        (new BinaryLogCommand())->event('te xt');
    }

    public function testEventNamesTheTypeOfARefusedEvent(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1730);
        $this->expectExceptionMessage('Only Format_description_log_event and row events are allowed in BINLOG statements (but Query was provided)');

        $session->query("BINLOG '" . base64_encode("\0\0\0\0\x02" . str_repeat("\0", 22)) . "'");
    }

    public function testEventNeedsAFormatDescriptionBeforeARowsQueryEvent(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1609);
        $this->expectExceptionMessage('The BINLOG statement of type `Rows_query` was not preceded by a format description BINLOG statement.');

        (new BinaryLogCommand())->event(base64_encode("\0\0\0\0\x1d" . str_repeat("\0", 22)));
    }

    public function testExecutePurgesNothingWithoutBinaryLogging(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->query("PURGE BINARY LOGS TO 'x'");
        $session->query("PURGE BINARY LOGS BEFORE '2020-01-01'");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }
}
