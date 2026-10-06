<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Operation;

#[CoversClass(ChangeReplicationSource::class)]
#[Medium]
final class ChangeReplicationSourceTest extends TestCase
{
    public function testDeriveStatementReportsRefusedSettings(): void
    {
        $change = (new Semantics(Dialect::MySql))->analyze("CHANGE REPLICATION SOURCE TO SOURCE_PASSWORD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', SOURCE_DELAY = 2147483648, SOURCE_HEARTBEAT_PERIOD = 5000000, REQUIRE_ROW_FORMAT = 2, SOURCE_CONNECTION_AUTO_FAILOVER = 1.5, ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = 'x' FOR CHANNEL 'a\\nb'");

        self::assertEquals([
            new RefusedSetting(ReplicationError::PasswordTooLong), new RefusedSetting(ReplicationError::DelayOutOfRange), new RefusedSetting(ReplicationError::HeartbeatOutOfRange),
            new RefusedSetting(ReplicationError::RowFormatValue), new RefusedSetting(ReplicationError::FractionalNumber), new RefusedSetting(ReplicationError::InvalidUuid),
            new RefusedSetting(ReplicationError::LineFeed),
        ], $change->facts->diagnostics);
    }

    public function testDeriveStatementRejectsTheVocabularyOfAnotherRelease(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
        $change = new ChangeReplicationSource(Terminology::Legacy, [new SourceOption(Terminology::Legacy, SourceOptionKind::Host, new Text('h'))]);

        $this->expectExceptionMessage('CHANGE REPLICATION SOURCE is written in the vocabulary of the release.');

        new Operation($semantics->context([]), $change);
    }

    public function testDeriveStatementRejectsAnOptionOfALaterRelease(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $change = new ChangeReplicationSource(Terminology::Legacy, [new SourceOption(Terminology::Legacy, SourceOptionKind::GtidOnly, new Numeral('1'))]);

        $this->expectExceptionMessage('GTID_ONLY is not an option of this release.');

        new Operation($semantics->context([]), $change);
    }

    public function testRenderWritesTheLegacyVocabulary(): void
    {
        self::assertSame("CHANGE MASTER TO MASTER_HOST = 'h', RELAY_LOG_POS = 4 FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("change master to master_host='h', relay_log_pos=4 for channel 'c'")->toString());
    }

    public function testRenderWritesTheCurrentVocabulary(): void
    {
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4", (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze("change replication source to master_log_file='f', source_log_pos=4")->toString());
    }
}
