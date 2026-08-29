<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;

#[CoversClass(LexicalKeywordIndex::class)]
final class LexicalKeywordIndexTest extends TestCase
{
    public function testReversedMapsEverySpellingBackToItsTerminal(): void
    {
        $index = (new LexicalKeywordIndex())->reversed([
            'SELECT_SYM' => ['SELECT'],
            'OR2_SYM' => ['||', 'OR'],
        ]);

        self::assertSame(
            ['SELECT' => 'SELECT_SYM', '||' => 'OR2_SYM', 'OR' => 'OR2_SYM'],
            $index,
        );
    }

    public function testReversedKeysSpellingsInUpperCase(): void
    {
        self::assertSame(
            ['SELECT' => 'SELECT_SYM'],
            (new LexicalKeywordIndex())->reversed(['SELECT_SYM' => ['select']]),
        );
    }

    public function testReversedLetsTheLastTerminalWinAContestedSpelling(): void
    {
        $index = (new LexicalKeywordIndex())->reversed([
            'FIRST_SYM' => ['SHARED'],
            'SECOND_SYM' => ['SHARED'],
        ]);

        self::assertSame(['SHARED' => 'SECOND_SYM'], $index);
    }

    public function testReversedIsEmptyForAProfileThatSpellsNothing(): void
    {
        self::assertSame([], (new LexicalKeywordIndex())->reversed([]));
    }
}
