<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Tokenization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Tokenization\KeywordIndex;

#[CoversClass(KeywordIndex::class)]
final class KeywordIndexTest extends TestCase
{
    public function testReversedMapsEverySpellingBackToItsTerminal(): void
    {
        $index = (new KeywordIndex())->reversed([
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
            (new KeywordIndex())->reversed(['SELECT_SYM' => ['select']]),
        );
    }

    public function testReversedLetsTheLastTerminalWinAContestedSpelling(): void
    {
        $index = (new KeywordIndex())->reversed([
            'FIRST_SYM' => ['SHARED'],
            'SECOND_SYM' => ['SHARED'],
        ]);

        self::assertSame(['SHARED' => 'SECOND_SYM'], $index);
    }

    public function testReversedIsEmptyForAProfileThatSpellsNothing(): void
    {
        self::assertSame([], (new KeywordIndex())->reversed([]));
    }



}
