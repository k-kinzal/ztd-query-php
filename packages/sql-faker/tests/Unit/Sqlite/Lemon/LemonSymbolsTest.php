<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lemon\LemonSymbols;

#[CoversClass(LemonSymbols::class)]
final class LemonSymbolsTest extends TestCase
{
    public function testDeclareTokenMakesALowercaseNameATerminal(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareToken('strict');

        self::assertTrue($symbols->isTerminal('strict'));
    }

    public function testDeclareRuleMakesACapitalisedNameANonTerminal(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareRule('SELECT');

        self::assertFalse($symbols->isTerminal('SELECT'));
    }

    public function testDeclareTokensOnReadsEveryNameADirectiveLists(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareTokensOn('SELECT INSERT DELETE.', '/\s+/');

        self::assertTrue($symbols->isTerminal('DELETE'));
    }

    public function testDeclareTokensOnIgnoresWhatIsNotSpelledLikeAToken(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareTokensOn('SELECT expr', '/\s+/');

        self::assertFalse($symbols->isTerminal('expr'));
    }

    public function testIsTerminalFallsBackToTheSpellingConventionForAnUnknownName(): void
    {
        $symbols = new LemonSymbols();

        self::assertTrue($symbols->isTerminal('SELECT'));
        self::assertFalse($symbols->isTerminal('expr'));
    }

    public function testIsTokenNameAcceptsANameWrittenInCapitals(): void
    {
        self::assertTrue(LemonSymbols::isTokenName('CREATE_TABLE2'));
    }

    public function testIsTokenNameRejectsANameThatIsNotWrittenInCapitals(): void
    {
        self::assertFalse(LemonSymbols::isTokenName('createTable'));
    }
}
