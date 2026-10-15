<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\SyntaxFormats;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SyntaxFormats::class)]
#[Medium]
final class SyntaxFormatsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string, bool}>
     */
    public static function providerFormats(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach (['YaML', 'JSON', 'TREE', 'TRADITIONAL'] as $name) {
                yield $release->value . ':' . $name => [$release, $name, $name === 'YaML' || ($name === 'TREE' && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true))];
            }
        }
    }

    #[DataProvider('providerFormats')]
    public function testCompletedRetainsFormatBeforeLaterInput(GrammarRelease $release, string $name, bool $refused): void
    {
        $sql = "EXPLAIN FORMAT = '" . $name . "' SELECT ?";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, (int) strpos($sql, '?'), new LanguageProfile($release));

        self::assertEquals($refused ? [new ParseFailure(new UnknownExplainFormat(new Name($name)), true)] : [], array_column($notices, 1));
    }
    /**
     * @return iterable<string, array{GrammarRelease, string, ?Name}>
     */
    public static function providerInto(): iterable
    {
        foreach ([GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value . ': implicit' => [$release, 'EXPLAIN INTO @p SELECT 1', null];
            yield $release->value . ': tree' => [$release, 'EXPLAIN FORMAT=TREE INTO @p SELECT 1', new Name('TREE')];
        }
    }

    #[DataProvider('providerInto')]
    public function testIntoRetainsTheOptionsWithoutPublishingAnInvalidStructure(GrammarRelease $release, string $sql, ?Name $format): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));

        self::assertEquals([new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto($format), true)], array_column($notices, 1));
    }

}
