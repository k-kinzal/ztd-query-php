<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\Replication\SourceChange;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceChange::class)]
#[Small]
final class SourceChangeTest extends TestCase
{
    public function testChannelRejectsOverlongChannelsBeforeGtidChecksWithoutInitializingState(): void
    {
        $session = (new Instance())->connect();
        $session->run("CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=1 FOR CHANNEL '" . str_repeat('猫', 65) . "'");

        self::assertSame([['Error', 3076, "Couldn't create channel: Channel name is either invalid or too long."]], $session->diagnostics->conditions);
        self::assertFalse($session->instance->registry->replication->initialized);
    }

    public function testCheckRequiresAnExistingPrivilegeChecksUser(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3926);
        $this->expectExceptionMessage("PRIVILEGE_CHECKS_USER for replication channel '' was set to `missing`@`%`, but this is not an existing user.");

        $session->query('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER=missing');
    }

    public function testCheckInitializesTheRepositoryBeforePrivilegeValidation(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->run('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER=missing');
        $session->query('START REPLICA SQL_THREAD');

        self::assertTrue($instance->registry->replication->applying);
    }

    public function testRelayAcceptsAnyPathWithTheIndexedBasename(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0');
        $file = $session->variables->read('relay_log') . '.000001';
        $session->query("CHANGE REPLICATION SOURCE TO RELAY_LOG_FILE='/elsewhere/" . $file . "'");

        self::assertFalse($session->instance->registry->replication->missing);
    }

    public function testRelayRefusesAnAbsentFileAndRetainsInitialization(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run("CHANGE REPLICATION SOURCE TO RELAY_LOG_FILE='missing'")[0];
        $replication = $session->instance->registry->replication;

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1380, $error->getCode());
        self::assertSame([true, true], [$replication->initialized, $replication->missing]);
    }

    public function testCheckRetainsEveryHeartbeatNoticeBeforeLegacyInitialization(): void
    {
        $session = (new Instance('5.7.44', ['server_id' => '0']))->connect();
        $session->run('CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=4294967, MASTER_HEARTBEAT_PERIOD=0');

        self::assertSame([1704, 1794], array_column($session->diagnostics->conditions, 1));

        $session->run('CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=0, MASTER_HEARTBEAT_PERIOD=4294967');

        self::assertSame([1704, 1794], array_column($session->diagnostics->conditions, 1));
    }

    public function testCheckUsesTheGlobalTimeout(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET GLOBAL replica_net_timeout=120');
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100');

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testCheckKeepsSubMillisecondNotices(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0.0001');

        self::assertSame([1703], array_column($session->diagnostics->conditions, 1));
    }

    public function testGtidsRefusesAutoPositionBeforeCredentials(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=1,SOURCE_USER='u',SOURCE_HEARTBEAT_PERIOD=100");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1704, 1777], array_column($session->diagnostics->conditions, 1));
    }

    public function testGtidsRequiresOnForAnonymousAssignment(): void
    {
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON_PERMISSIVE']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4015);

        $session->query('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=LOCAL');
    }

    public function testWarningsPrecedeCompressionFailure(): void
    {
        $session = (new Instance())->connect();
        $session->run("CHANGE REPLICATION SOURCE TO SOURCE_USER='u',SOURCE_LOG_FILE='f',SOURCE_HEARTBEAT_PERIOD=100,SOURCE_COMPRESSION_ALGORITHMS='bad'");

        self::assertSame([1704, 3023, 1759, 1760, 3920], array_column($session->diagnostics->conditions, 1));
    }

    public function testWarningsRecordTheTopologyNoteOnlyForEnabledAssignment(): void
    {
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON']))->connect();
        $session->query('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=LOCAL');
        self::assertSame([['Note', 4017, AdministrationError::AnonymousAssignmentTopology->message()]], $session->diagnostics->conditions);

        $session->query('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=OFF');
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testCompressionAcceptsCaseDuplicatesAndTrailingComma(): void
    {
        (new SourceChange())->compression('ZLIB,zlib,zstd,', '');

        self::expectNotToPerformAssertions();
    }

    public function testCompressionCountsDuplicatesBeforeValidatingNames(): void
    {
        $this->expectExceptionCode(3924);
        $this->expectExceptionMessage("Specified compression algorithm list 'ZLIB,zlib,zstd,uncompressed,' exceeds total count of 3 for channel ''.");
        (new SourceChange())->compression('ZLIB,zlib,zstd,uncompressed,', '');
    }

    public function testCompressionChecksByteLengthBeforeCountingOrNames(): void
    {
        $this->expectExceptionCode(4037);
        $this->expectExceptionMessage("Value too long setting SOURCE_COMPRESSION_ALGORITHMS option to a 99 chars long string for channel 'channel'.");
        (new SourceChange())->compression(str_repeat('猫', 33), 'channel');
    }

    public function testCompressionIgnoresBytesAfterNul(): void
    {
        (new SourceChange())->compression("zlib\0" . str_repeat('x', 150), '');
        self::expectNotToPerformAssertions();
    }

    public function testCheckRetainsEarlierSettingsWhenTheFinalCompressionValueFails(): void
    {
        $option = SourceOption::class;
        $kind = SourceOptionKind::class;
        $vocabulary = Terminology::Current;
        $text = Text::class;
        $statement = new ChangeReplicationSource($vocabulary, [new $option($vocabulary, $kind::Host, new $text('local')), new $option($vocabulary, $kind::CompressionAlgorithms, new $text(str_repeat('x', 129)))]);
        $session = (new Instance())->connect();
        $session->text = 'unrelated request label';
        $statement1 = $statement;
        $parameters1 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $result = $result1;
        self::assertSame([4037, 'HY000', "Value too long setting SOURCE_COMPRESSION_ALGORITHMS option to a 129 chars long string for channel ''."], $result instanceof SqlError ? [$result->getCode(), $result->sqlState(), $result->getMessage()] : null);
        self::assertSame('local', $session->instance->registry->replication->host);
        self::assertSame('unrelated request label', $session->text);
    }

    public function testCompressionNamesTheFirstBadElement(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3920);
        $this->expectExceptionMessage("Invalid SOURCE_COMPRESSION_ALGORITHMS ' zstd' for channel 'channel'.");

        (new SourceChange())->compression('zlib, zstd', 'channel');
    }
}
