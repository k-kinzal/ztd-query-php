<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemLayout;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ItemLayout::class)]
#[Medium]
final class ItemLayoutTest extends TestCase
{
    public function testOfKeepsTheTokensAndTheTriviaOfTheExpression(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT 1 /* c */ +  2 , 3')->find('expr')[0];

        self::assertSame('1 /* c */ +  2', (new ItemLayout(GrammarRelease::MySql847))->of($node)->text());
    }

    public function testOfDropsTheMarkersOfAVersionComment(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT 1 /*!50000 +2 */ + 3')->find('expr')[0];

        self::assertSame('1  +2  + 3', (new ItemLayout(GrammarRelease::MySql847))->of($node)->text());
    }

    public function testGapWritesTheClosingMarkerAsASpaceBetweenTwoCharacters(): void
    {
        $layout = new ItemLayout(GrammarRelease::MySql847);

        self::assertSame(' ', $layout->gap('*/', '2', '+'));
        self::assertSame(' ', $layout->gap(' */', '2', '+'));
        self::assertSame('', $layout->gap('/*!50000', '1', '+'));
        self::assertSame(' -- x', $layout->gap(' -- x', 'a', 'b'));
    }

    public function testOpeningSkipsAVersionCommentForALaterRelease(): void
    {
        $layout = new ItemLayout(GrammarRelease::MySql5744);

        self::assertSame(14, $layout->opening('/*!80000 +2 */ ', 0));
        self::assertSame(8, $layout->opening('/*!50000 +2 */ ', 0));
        self::assertSame(3, $layout->opening('/*! +2 */ ', 0));
    }

    public function testDigitsReadsSixDigitsFromMySql81(): void
    {
        self::assertSame('100000', (new ItemLayout(GrammarRelease::MySql847))->digits('/*!100000 1', 3));
        self::assertSame('10000', (new ItemLayout(GrammarRelease::MySql8044))->digits('/*!100000 1', 3));
        self::assertSame('', (new ItemLayout(GrammarRelease::MySql847))->digits('/*! 1', 3));
    }

    public function testTriviaAnswersTheEndOfOneTriviaElement(): void
    {
        $layout = new ItemLayout(GrammarRelease::MySql847);

        self::assertSame(7, $layout->trivia('/* c */ ', 0));
        self::assertSame(3, $layout->trivia("# x\n", 0));
        self::assertSame(1, $layout->trivia('  ', 0));
    }

    public function testVersionAnswersTheServerVersionNumber(): void
    {
        self::assertSame(50651, (new ItemLayout(GrammarRelease::MySql5651))->version());
        self::assertSame(90100, (new ItemLayout(GrammarRelease::MySql910))->version());
    }

    public function testWordsSplitsTheLexemeOfWithRollup(): void
    {
        $layout = new ItemLayout(GrammarRelease::MySql5744);

        self::assertSame([[' ', 'WITH'], [' /* c */ ', 'ROLLUP']], $layout->words(' ', 'WITH /* c */ ROLLUP'));
        self::assertSame([[' ', 'a']], $layout->words(' ', 'a'));
    }
}
