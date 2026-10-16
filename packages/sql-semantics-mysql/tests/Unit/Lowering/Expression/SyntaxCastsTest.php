<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxCasts;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxCasts::class)]
#[Medium]
final class SyntaxCastsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|ParseFailure>}>
     */
    public static function providerCasts(): iterable
    {
        yield 'datetime precision' => ['SELECT CAST(1 AS DATETIME(7)),?', [new ParseFailure(new TooBigPrecision(7, 'CAST'), true)]];
        yield 'float precision' => ['SELECT CAST(1 AS FLOAT(54)),?', [new ParseFailure(new TooBigPrecision(54, 'CAST', 53), true)]];
        yield 'convert precision' => ['SELECT CONVERT(1,DATETIME(7)),?', [new ParseFailure(new TooBigPrecision(7, 'CAST'), true)]];
        yield 'binary stops after local operand' => ['SELECT BINARY CAST(1 AT LOCAL AS SIGNED),?', [new ParseFailure(new NotSupportedYet('AT LOCAL')), new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(Deprecated::BinaryOperator, aborts: true)]];
        yield 'incomplete cast' => ['SELECT CAST(1 AS DATETIME(7),?', []];
        yield 'array kind precedes precision' => ['SELECT CAST(1 AS FLOAT(54) ARRAY),?', [new ParseFailure(new NotSupportedYet('CAST-ing data to array of FLOAT'), true)]];
        yield 'json array' => ['SELECT CAST(1 AS JSON ARRAY),?', [new ParseFailure(new NotSupportedYet('CAST-ing data to array of JSON'), true)]];
        yield 'incomplete array' => ['SELECT CAST(1 AS JSON ARRAY,?', []];
        yield 'local zone continues' => ['SELECT CAST(1 AT LOCAL AS DATETIME(7)),?', [new ParseFailure(new NotSupportedYet('AT LOCAL'))]];
        yield 'explicit zone precision' => ["SELECT CAST(1 AT TIME ZONE '+00:00' AS DATETIME(7)),?", [new ParseFailure(new TooBigPrecision(7, 'CAST'), true)]];
        yield 'national cast' => ['SELECT CAST(1 AS NCHAR),?', [Deprecated::National]];
        yield 'national array' => ['SELECT CAST(1 AS NCHAR ARRAY),?', [Deprecated::National, new ParseFailure(new NotSupportedYet('specifying charset for multi-valued index'), true)]];
        yield 'national target completes first' => ['SELECT CAST(1 AS NCHAR,?', [Deprecated::National]];
        yield 'valid target' => ['SELECT CAST(1 AS SIGNED),?', []];
    }

    /**
     * @param list<Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|ParseFailure> $expected The completed notices
     */
    #[DataProvider('providerCasts')]
    public function testCompletedUsesOnlyCompletedTargetsAndCasts(string $sql, array $expected): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), $profile);

        self::assertEquals($expected, array_column($notices, 1));
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerContinuingReleases(): iterable
    {
        yield '8.0' => [GrammarRelease::MySql8044];
        yield '8.1' => [GrammarRelease::MySql810];
    }

    #[DataProvider('providerContinuingReleases')]
    public function testCompletedContinuesAfterCastErrorsInEarlyModernReleases(GrammarRelease $release): void
    {
        $sql = 'SELECT CAST(1 AS DATETIME(7)), CAST(1 AS JSON ARRAY), BINARY 1,?';
        $profile = new LanguageProfile($release);
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, (int) strpos($sql, '?'), $profile);

        self::assertEquals([new ParseFailure(new TooBigPrecision(7, 'CAST')), new ParseFailure(new NotSupportedYet('CAST-ing data to array of JSON')), Deprecated::BinaryOperator], array_column($notices, 1));
    }
}
