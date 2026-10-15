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
    /**
     * @return iterable<string, array{GrammarRelease, string, list<ParseFailure>}>
     */
    public static function providerPrefixes(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            $prefix = $release === GrammarRelease::MySql8044 ? 'RESET MASTER' : 'RESET BINARY LOGS AND GTIDS';
            yield $release->value . ': zero' => [$release, $prefix . ' TO 0, QUERY CACHE', [new ParseFailure(new RefusedSetting(ReplicationError::FileNumberOutOfRange, new Numeral('0')), true)]];
            yield $release->value . ': large' => [$release, $prefix . ' TO 2000000001, SELECT', [new ParseFailure(new RefusedSetting(ReplicationError::FileNumberOutOfRange, new Numeral('2000000001')), true)]];
            yield $release->value . ': earlier line feed' => [$release, "RESET REPLICA FOR CHANNEL 'a\nb', " . ($release === GrammarRelease::MySql8044 ? 'MASTER' : 'BINARY LOGS AND GTIDS') . ' TO 0', [new ParseFailure(new RefusedSetting(ReplicationError::LineFeed, text: new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb")), true)]];
            yield $release->value . ': accepted' => [$release, $prefix . ' TO 2000000000, SELECT', []];
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
