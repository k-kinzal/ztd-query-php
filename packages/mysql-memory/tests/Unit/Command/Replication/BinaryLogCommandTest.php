<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\BinaryLogCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryLogCommand::class)]
#[Small]
final class BinaryLogCommandTest extends TestCase
{
    public function testExecuteDoesNotCountRetainedLegacyDiagnosticsAsNewWarnings(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('EXECUTE absent');
        $reply = $session->query("PURGE BINARY LOGS BEFORE '2020-01-01'")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(0, $reply->warnings);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1243', 'Unknown prepared statement handler (absent) given to EXECUTE']], $warnings->rows);
    }

    /**
     * @param list<int> $files
     * @param list<list<string>> $warnings
     */
    #[DataProvider('providerRotatedCutoffs')]
    public function testExecutePurgesOnlyClosedFilesBeforeTheCutoff(string $version, string $cutoff, array $files, array $warnings): void
    {
        $session = (new Instance($version))->connect();
        $session->query("SET sql_mode='', time_zone='+00:00', timestamp=1700000000; FLUSH BINARY LOGS");
        $session->query('PURGE BINARY LOGS BEFORE ' . $cutoff);
        $reply = $session->query('SHOW WARNINGS')[0];

        self::assertSame($files, $session->instance->registry->binaryLog->files);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame($warnings, $reply->rows);
    }

    /**
     * @return iterable<string, array{string, string, list<int>, list<list<string>>}>
     */
    public static function providerRotatedCutoffs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $logging = !str_starts_with($version, '5.');
            $kept = $logging ? [1, 2] : [1];
            yield $version . '/invalid' => [$version, "'garbage'", $kept, [['Warning', '1292', "Incorrect datetime value: 'garbage'"]]];
            yield $version . '/past' => [$version, "'2020-01-01'", $kept, []];
            yield $version . '/clock' => [$version, 'CURRENT_TIMESTAMP', $kept, []];
            yield $version . '/future' => [$version, "'2030-01-01'", $logging ? [2] : [1], $logging ? [['Warning', '1868', 'file ./binlog.000002 was not purged because it is the active log file.']] : []];
        }
    }

    public function testMomentConvertsACompletedTruthTestAndRestoresItsErrorScope(): void
    {
        $session = (new Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $operand = $this->createMock(\MySqlMemory\Evaluation\Evaluable::class);
        $operand->method('domain')->willReturn(\MySqlMemory\Typing\Domain::null());
        $operand->method('evaluate')->willThrowException(\MySqlMemory\Error\Family\StatementError::WrongArguments->error('ESCAPE'));
        $moment = new \MySqlMemory\Evaluation\Operator\Comparison\IsTest($operand, null, false, \MySqlMemory\Typing\Domain::integer());

        try {
            (new BinaryLogCommand())->moment($moment, $context);
            self::fail('The recorded evaluation error must still terminate PURGE.');
        } catch (SqlError $error) {
            self::assertSame(1210, $error->getCode());
            self::assertTrue($error->recorded);
        }
        self::assertSame([['Error', 1210, 'Incorrect arguments to ESCAPE'], ['Warning', 1292, "Incorrect datetime value: '1'"]], $session->diagnostics->conditions);
        self::assertFalse($context->continueAfterError);
        self::assertNull($context->deferredError);
    }

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

    public function testReplayAcceptsARowsQueryAnnotationWithAnEstablishedLayout(): void
    {
        $session = (new Instance())->connect();
        $session->binlog->format = new \MySqlMemory\Command\Replication\Binlog\Format(19, '', 0);
        $reply = $session->query("BINLOG '" . base64_encode(pack('VCVVVv', 0, 29, 1, 23, 0, 0) . "\x03abc") . "'")[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
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
