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
use SqlSemantics\Statement\Type\Known;

#[CoversClass(SourceSettings::class)]
#[Medium]
final class SourceSettingsTest extends TestCase
{
    public function testWarningResolvesEachOccurrenceAgainstTheGlobalTimeout(): void
    {
        $rule = new SourceSettings();
        $option = new SourceOption(Terminology::Current, SourceOptionKind::HeartbeatPeriod, new NumberLiteral('100'));

        self::assertNull($rule->warning($option, GrammarRelease::MySql847, 200));
        self::assertSame(1704, $rule->warning($option, GrammarRelease::MySql847, 10)?->code());
        self::assertNull($rule->warning($option, GrammarRelease::MySql5651));
        self::assertStringContainsString('slave_net_timeout', $rule->warning($option, GrammarRelease::MySql5744)?->message() ?? '');
        self::assertSame(1703, $rule->warning(new SourceOption(Terminology::Current, SourceOptionKind::HeartbeatPeriod, new NumberLiteral('0.0001')), GrammarRelease::MySql847)?->code());
    }

    public function testOptionsRetainsDistinctNoticesForRepeatedSettings(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100, SOURCE_HEARTBEAT_PERIOD=100, SOURCE_HEARTBEAT_PERIOD=0');

        self::assertCount(2, $operation->warnings());
        self::assertEquals($operation->warnings()[0], $operation->warnings()[1]);
        self::assertNotSame($operation->warnings()[0], $operation->warnings()[1]);
    }

    public function testOptionsDerivesTheHeartbeatPeriod(): void
    {
        $change = (new Semantics(Dialect::MySql))->analyze('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD = 2.5');

        self::assertInstanceOf(ChangeReplicationSource::class, $change);
        self::assertInstanceOf(NumberLiteral::class, $change->options[0]->value);
        self::assertInstanceOf(Known::class, $change->options[0]->value->scalarInformation()->type);
    }

    public function testOptionRejectsAnOptionOfALaterRelease(): void
    {
        $this->expectExceptionMessage('SOURCE_TLS_VERSION is not an option of this release.');

        (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), new ChangeReplicationSource(Terminology::Legacy, [new SourceOption(Terminology::Legacy, SourceOptionKind::TlsVersion, new Text('TLSv1.2'))]), new \SqlSemantics\Statement\Source\SourceMap());
    }

    public function testErrorAnswersTheCheckOfEachValueKind(): void
    {
        $settings = new SourceSettings();

        self::assertNull($settings->error(new SourceOption(Terminology::Current, SourceOptionKind::HeartbeatPeriod, new NumberLiteral('4294967')), GrammarRelease::MySql847));
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
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new SourceSettings())->lineFeed($derivation, new Text("a\nb"));

        self::assertCount(1, $derivation->facts()->diagnostics);
    }
    public function testHeartbeatDistinguishesTheInclusiveUpperBoundary(): void
    {
        $rule = new SourceSettings();

        self::assertNull($rule->heartbeat(new NumberLiteral('0')));
        self::assertNull($rule->heartbeat(new NumberLiteral('4294967')));
        self::assertSame(ReplicationError::HeartbeatOutOfRange, $rule->heartbeat(new NumberLiteral('4294967.5')));
        self::assertSame(ReplicationError::HeartbeatOutOfRange, $rule->heartbeat(new NumberLiteral('1e300')));
    }

    public function testConditionRetainsTheNumeralAndItsInputPosition(): void
    {
        $number = new Numeral('2', inputSource: new \SqlSemantics\Statement\Source\Span(59, 1));
        $condition = (new SourceSettings())->condition(new SourceOption(Terminology::Current, SourceOptionKind::GtidOnly, $number), GrammarRelease::MySql847);

        self::assertNotNull($condition);
        self::assertTrue($condition->aborts);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber(SourceOptionKind::GtidOnly, $number, 59), $condition->problem);
    }

    public function testConditionKeepsNonAbortingSourceSwitchesAndAbsentConditions(): void
    {
        $settings = new SourceSettings();
        $condition = $settings->condition(new SourceOption(Terminology::Current, SourceOptionKind::RequireRowFormat, new Numeral('2')), GrammarRelease::MySql847);

        self::assertNotNull($condition);
        self::assertFalse($condition->aborts);
        self::assertNull($settings->condition(new SourceOption(Terminology::Current, SourceOptionKind::GtidOnly, new Numeral('1')), GrammarRelease::MySql847));
    }

}
