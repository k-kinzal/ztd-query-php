<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintLexer;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintRefusal;

#[CoversClass(HintLexer::class)]
#[Small]
final class HintLexerTest extends TestCase
{
    public function testTokenSkipsWhitespace(): void
    {
        self::assertSame(['symbol', '(', 2, 3], (new HintLexer(" \t(", 0, GrammarRelease::MySql847, false))->token());
    }

    public function testScanReadsWordsNumbersAndQuotes(): void
    {
        $lexer = new HintLexer('bka 1t 12 "x" `y`', 0, GrammarRelease::MySql847, true);

        self::assertSame(['keyword', 'BKA', 0, 3], $lexer->scan(0, true));
        self::assertSame(['name', 'bka', 0, 3], $lexer->scan(0, false));
        self::assertSame(['name', '1t', 4, 6], $lexer->scan(4, true));
        self::assertSame(['integer', '12', 7, 9], $lexer->scan(7, true));
        self::assertSame(['name', 'x', 10, 13], $lexer->scan(10, true));
        self::assertSame(['name', 'y', 14, 17], $lexer->scan(14, true));
    }

    public function testWordTellsTheBytesOfAnUnquotedWord(): void
    {
        self::assertSame([true, true, true, false, false], [HintLexer::word('$'), HintLexer::word("\xC3"), HintLexer::word('9'), HintLexer::word('.'), HintLexer::word('')]);
    }

    public function testNumberRefusesAPointWithoutAFraction(): void
    {
        $this->expectException(HintRefusal::class);

        (new HintLexer('1.)', 0, GrammarRelease::MySql847, false))->number(0, '1');
    }

    public function testQuotedRefusesAnEmptyString(): void
    {
        $this->expectException(HintRefusal::class);

        (new HintLexer("''", 0, GrammarRelease::MySql847, false))->quoted(0, "'");
    }

    public function testKeywordKnowsTheHintsAndStrategiesOfTheRelease(): void
    {
        $lexer = new HintLexer('', 0, GrammarRelease::MySql5744, false);

        self::assertSame([true, true, false], [$lexer->keyword('BKA'), $lexer->keyword('DUPSWEEDOUT'), $lexer->keyword('INDEX')]);
    }

    public function testRefuseMarksTheEndOfTheComment(): void
    {
        $lexer = new HintLexer('ab', 5, GrammarRelease::MySql847, false);

        self::assertSame([6, false], [$lexer->refuse(1)->error->offset, $lexer->refuse(1)->error->closing]);
        self::assertTrue($lexer->refuse(2)->error->closing);
    }

    public function testScaleMultipliesBy1024(): void
    {
        self::assertSame(['1024', '1073741824', '0'], [HintLexer::scale('1', 1), HintLexer::scale('1', 3), HintLexer::scale('0', 2)]);
    }
}
