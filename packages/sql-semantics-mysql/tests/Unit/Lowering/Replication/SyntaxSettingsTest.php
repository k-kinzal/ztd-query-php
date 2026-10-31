<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Replication\SyntaxSettings;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(SyntaxSettings::class)]
#[Medium]
final class SyntaxSettingsTest extends TestCase
{
    public function testWarnedRetainsAnEarlierPeriodWhenLaterInputFails(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $parser = new MySqlParser($profile->grammar->value);
        $sql = "CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100, SOURCE_HEARTBEAT_PERIOD=100 FOR CHANNEL '\n'";
        $warnings = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile, 10);

        self::assertCount(3, $warnings);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod(\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::AboveReplicaTimeout), $warnings[0][1]);
        self::assertEquals($warnings[0][1], $warnings[1][1]);
        self::assertInstanceOf(ParseFailure::class, $warnings[2][1]);
        self::assertCount(1, (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile, 200));
    }

    public function testRejectedLeavesUuidValidationToTheCompleteOwner(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $tree = (new MySqlParser($profile->grammar->value))->parse("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS='bad'");
        $refusal = (new SyntaxWarnings())->rejected($tree, $profile);

        self::assertNull($refusal);
    }

    public function testConvertedRetainsAChannelConversionBeforeALaterSyntaxError(): void
    {
        $sql = "CHANGE REPLICATION SOURCE TO SOURCE_LOG_POS=4 FOR CHANNEL '😀' trailing";
        $release = GrammarRelease::MySql847;
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));

        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new \SqlSemantics\Statement\Identifier\Name('😀'))], array_column($warnings, 1));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string, list<ParseFailure>}>
     */
    public static function providerPrefixes(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            $prefix = $release === GrammarRelease::MySql8044 ? 'RESET MASTER' : 'RESET BINARY LOGS AND GTIDS';
            yield $release->value . ': zero' => [$release, $prefix . ' TO 0, QUERY CACHE', [new ParseFailure(new RefusedSetting(ReplicationError::FileNumberOutOfRange, new Numeral('0', inputSource: new \SqlSemantics\Statement\Source\Span(strlen($prefix) + 4, 1))), true)]];
            yield $release->value . ': large' => [$release, $prefix . ' TO 2000000001, SELECT', [new ParseFailure(new RefusedSetting(ReplicationError::FileNumberOutOfRange, new Numeral('2000000001', inputSource: new \SqlSemantics\Statement\Source\Span(strlen($prefix) + 4, 10))), true)]];
            yield $release->value . ': earlier line feed' => [$release, "RESET REPLICA FOR CHANNEL 'a\nb', " . ($release === GrammarRelease::MySql8044 ? 'MASTER' : 'BINARY LOGS AND GTIDS') . ' TO 0', [new ParseFailure(new RefusedSetting(ReplicationError::LineFeed, text: new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb")), true)]];
            yield $release->value . ': invalid UUID' => [$release, "CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS='invalid' trailing", [new ParseFailure(new RefusedSetting(ReplicationError::InvalidUuid, text: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('invalid', quotation: new \SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation())), true)]];
            yield $release->value . ': heartbeat' => [$release, 'CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=18446744073709551615 trailing', [new ParseFailure(new RefusedSetting(ReplicationError::HeartbeatOutOfRange), true)]];
            yield $release->value . ': empty group user' => [$release, "START GROUP_REPLICATION USER='' trailing", [new ParseFailure(new RefusedSetting(ReplicationError::GroupUserEmpty), true)]];
            yield $release->value . ': long group password' => [$release, "START GROUP_REPLICATION PASSWORD='" . str_repeat('p', 33) . "' trailing", [new ParseFailure(new RefusedSetting(ReplicationError::GroupPasswordTooLong), true)]];
            yield $release->value . ': accepted'  => [$release, $prefix . ' TO 2000000000, SELECT', []];
        }
    }

    /**
     * @param list<ParseFailure> $expected The completed conditions before the later refusal
     */
    #[DataProvider('providerPrefixes')]
    public function testCompletedRetainsTheNumericOperandBeforeALaterRefusal(GrammarRelease $release, string $sql, array $expected): void
    {
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));

        self::assertEquals($expected, array_column($warnings, 1));
    }
}
