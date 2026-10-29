<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\SourceSwitches;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceSwitches::class)]
#[Small]
final class SourceSwitchesTest extends TestCase
{
    #[DataProvider('providerLegacyRunningApplier')]
    public function testThreadsUseTheReleaseSpecificRefusal(string $version, string $option, int $code, string $message): void
    {
        $session = (new Instance($version, ['server_id' => 1]))->connect();
        $session->query("CHANGE MASTER TO MASTER_HOST='127.0.0.1', MASTER_HEARTBEAT_PERIOD=0; START SLAVE SQL_THREAD");
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        $session->query('CHANGE MASTER TO ' . $option);
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function providerLegacyRunningApplier(): iterable
    {
        foreach (['MASTER_DELAY=1', 'RELAY_LOG_POS=4', 'MASTER_AUTO_POSITION=0', 'MASTER_HEARTBEAT_PERIOD=0'] as $option) {
            yield "5.6.51 $option" => ['5.6.51', $option, 1198, 'This operation cannot be performed with a running slave; run STOP SLAVE first'];
        }
        foreach (['5.7.44' => 'SLAVE', '8.0.44' => 'REPLICA'] as $version => $word) {
            foreach (['MASTER_DELAY=1', 'RELAY_LOG_POS=4'] as $option) {
                $name = strtolower($word);
                yield "$version $option" => [$version, $option, 3085, "This operation cannot be performed with a running $name sql thread; run STOP $word SQL_THREAD FOR CHANNEL '' first."];
            }
            yield "$version all threads" => [$version, 'MASTER_AUTO_POSITION=0', 3081, "This operation cannot be performed with running replication threads; run STOP $word FOR CHANNEL '' first"];
        }
    }

    #[DataProvider('providerRunningApplierOptions')]
    public function testThreadsRejectChangesToTheRunningApplier(string $option, int $code): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; START REPLICA SQL_THREAD');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query('CHANGE REPLICATION SOURCE TO ' . $option);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerRunningApplierOptions(): iterable
    {
        foreach (['REQUIRE_TABLE_PRIMARY_KEY_CHECK=ON', 'REQUIRE_ROW_FORMAT=1', 'PRIVILEGE_CHECKS_USER=NULL', "RELAY_LOG_FILE='bad'", 'RELAY_LOG_POS=4', 'SOURCE_DELAY=1'] as $option) {
            yield $option => [$option, 3085];
        }
        foreach (['SOURCE_AUTO_POSITION=0', 'SOURCE_AUTO_POSITION=1', 'GTID_ONLY=0', 'GTID_ONLY=1', 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=OFF'] as $option) {
            yield $option => [$option, 3081];
        }
    }

    public function testThreadsAllowReceiverSettingsToChangeWhileTheApplierRuns(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; START REPLICA SQL_THREAD; CHANGE REPLICATION SOURCE TO SOURCE_HOST='test'");
        self::assertTrue($session->instance->registry->replication->applying);
        self::assertSame('test', $session->instance->registry->replication->host);
    }

    public function testCheckRequiresGtidModeBeforeFailover(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::ConnectionAutoFailover, new Numeral('1'));
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3990);
        (new SourceSwitches())->check([$option->kind->value => $option], $session, '');
    }

    public function testApplyPreservesPreviouslyConfiguredSwitches(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::GtidOnly, new Numeral('1'));
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON']))->connect();
        $state = $session->instance->registry->replication;
        $state->autoPosition = true;
        $state->requireRowFormat = true;
        $settings = new SourceSwitches();
        $settings->check([$option->kind->value => $option], $session, '');
        $settings->apply([$option->kind->value => $option], $session);

        self::assertSame([true, true, true, false], [$state->autoPosition, $state->requireRowFormat, $state->gtidOnly, $state->autoFailover]);
    }

    public function testValueUsesTheNumericOptionInterpretation(): void
    {
        $option = new SourceOption(Terminology::Current, SourceOptionKind::RequireRowFormat, new Numeral('0.9'));
        $settings = new SourceSwitches();

        self::assertFalse($settings->value([$option->kind->value => $option], $option->kind, true));
        self::assertTrue($settings->value([], $option->kind, true));
    }
}
