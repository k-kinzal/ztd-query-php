<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\ReplicaCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;

#[CoversClass(ReplicaCommand::class)]
#[Small]
final class ReplicaCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ReplicaCommand())->clearsDiagnostics());
    }

    public function testExecuteCannotStartTheReceiverThread(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1200);
        $this->expectExceptionMessage('The server is not configured as replica; fix in config file or with CHANGE REPLICATION SOURCE TO');

        $session->query('START REPLICA');
    }

    public function testExecuteNotesAPlainTextPasswordFirst(): void
    {
        $session = (new Instance())->connect();
        $session->run("START REPLICA USER = 'u' PASSWORD = 'p'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1759', 'Sending passwords in plain text without SSL/TLS is extremely insecure.'], ['Error', '1200', 'The server is not configured as replica; fix in config file or with CHANGE REPLICATION SOURCE TO']], $warnings->rows);
    }

    public function testExecuteRunsTheApplierThreadUntilStopReplica(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query('START REPLICA SQL_THREAD');
        $running = $instance->registry->applying;
        $session->query('STOP REPLICA');
        $session->query('STOP REPLICA');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([true, false], [$running, $instance->registry->applying]);
        self::assertSame([['Note', '3084', "Replication thread(s) for channel '' are already stopped."]], $warnings->rows);
    }

    public function testExecuteRefusesAFilterForAChannel(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1794);

        $session->query("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a) FOR CHANNEL ''");
    }

    public function testExecuteRefusesGroupReplicationInsideATransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1192);

        $session->query('START GROUP_REPLICATION');
    }

    public function testExecuteFindsGroupReplicationUnconfigured(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3092);
        $this->expectExceptionMessage('The server is not configured properly to be an active member of the group. Please see more details on error log.');

        $session->query('STOP GROUP_REPLICATION');
    }

    public function testResetStartsTheBinaryLogAgain(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('RESET BINARY LOGS AND GTIDS TO 06344632')->statement;
        self::assertInstanceOf(Reset::class, $statement);
        $registry = new Registry();
        (new ReplicaCommand())->reset($statement->targets[0], $registry);

        self::assertSame('binlog.6344632', $registry->binaryLog->active());
    }

    public function testResetRefusesAReplicaWhoseApplierRuns(): void
    {
        $session = (new Instance())->connect();
        $session->query('START REPLICA SQL_THREAD');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3081);
        $this->expectExceptionMessage("This operation cannot be performed with running replication threads; run STOP REPLICA FOR CHANNEL '' first");

        $session->query('RESET REPLICA ALL');
    }

    public function testChannelNamesAMissingChannelInLowerCase(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3074);
        $this->expectExceptionMessage("Replica channel 'x>_q,8[utow' does not exist.");

        (new ReplicaCommand())->channel(new Text('x>_q,8[uTow'));
    }

    public function testChannelRefusesALineFeed(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect argument contains not-allowed LF value: 'a\nb'");

        $session->query("SHOW REPLICA STATUS FOR CHANNEL 'a\nb'");
    }
}
