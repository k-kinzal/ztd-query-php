<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(SourceSettings::class)]
#[Medium]
final class SourceSettingsTest extends TestCase
{
    public function testOptionsDerivesTheHeartbeatPeriod(): void
    {
        $change = (new Semantics(Dialect::MySql))->analyze('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD = 2.5');

        self::assertInstanceOf(ChangeReplicationSource::class, $change->statement);
        self::assertInstanceOf(NumberLiteral::class, $change->statement->options[0]->value);
        self::assertInstanceOf(Known::class, $change->facts->scalar($change->statement->options[0]->value)->type);
    }

    public function testOptionRejectsAnOptionOfALaterRelease(): void
    {
        $this->expectExceptionMessage('NETWORK_NAMESPACE is not an option of this release.');

        new Operation(
            (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]),
            new ChangeReplicationSource(Terminology::Legacy, [new SourceOption(Terminology::Legacy, SourceOptionKind::NetworkNamespace, new Text('n'))]),
        );
    }

    public function testErrorAnswersTheCheckOfEachValueKind(): void
    {
        $settings = new SourceSettings();

        self::assertSame(ReplicationError::HeartbeatOutOfRange, $settings->error(new SourceOption(Terminology::Current, SourceOptionKind::HeartbeatPeriod, new NumberLiteral('4294967.5')), GrammarRelease::MySql847));
        self::assertNull($settings->error(new SourceOption(Terminology::Current, SourceOptionKind::PrivilegeChecksUser, null), GrammarRelease::MySql847));
    }

    public function testTextAnswersTheStringChecks(): void
    {
        $settings = new SourceSettings();

        self::assertSame(ReplicationError::LineFeed, $settings->text(SourceOptionKind::Host, new Text("a\nb"), GrammarRelease::MySql847));
        self::assertNull($settings->text(SourceOptionKind::CompressionAlgorithms, new Text("a\nb"), GrammarRelease::MySql847));
        self::assertNull($settings->text(SourceOptionKind::Password, new Text(str_repeat('a', 33)), GrammarRelease::MySql5651));
        self::assertNull($settings->text(SourceOptionKind::AssignGtidsToAnonymousTransactions, new Text('3e11fa47-71ca-11e1-9e33-c80aa9429562 and more'), GrammarRelease::MySql847));
    }

    public function testNumberAnswersTheNumberChecks(): void
    {
        $settings = new SourceSettings();

        self::assertSame(ReplicationError::SwitchValue, $settings->number(SourceOptionKind::GtidOnly, new Numeral('2')));
        self::assertNull($settings->number(SourceOptionKind::RequireRowFormat, new Numeral('1.9')));
        self::assertNull($settings->number(SourceOptionKind::Delay, new Numeral('2147483647')));
    }

    public function testLineFeedReportsALineFeed(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze("STOP REPLICA FOR CHANNEL 'a\\nb'")->facts->diagnostics);
    }
}
