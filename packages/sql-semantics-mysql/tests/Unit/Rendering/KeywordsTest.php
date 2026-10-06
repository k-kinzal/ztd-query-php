<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Keywords;

#[CoversClass(Keywords::class)]
#[Small]
final class KeywordsTest extends TestCase
{
    public function testReservedIsTrueForKeywordsOfTheReleaseWhateverTheCase(): void
    {
        $keywords = new Keywords(GrammarRelease::MySql8044);

        self::assertTrue($keywords->reserved('select'));
        self::assertTrue($keywords->reserved('SELECT'));
        self::assertTrue($keywords->reserved('SeLeCt'));
        self::assertTrue($keywords->reserved('order'));
        self::assertTrue($keywords->reserved('action'));
        self::assertTrue($keywords->reserved('integer'));
        self::assertTrue($keywords->reserved('schema'));
        self::assertTrue($keywords->reserved('fields'));
    }

    public function testReservedIsTrueForFunctionNamesOfTheRelease(): void
    {
        $keywords = new Keywords(GrammarRelease::MySql8044);

        self::assertTrue($keywords->reserved('count'));
        self::assertTrue($keywords->reserved('COUNT'));
        self::assertTrue($keywords->reserved('adddate'));
    }

    public function testReservedIsTrueForWordsStartingWithAnUnderscore(): void
    {
        self::assertTrue((new Keywords(GrammarRelease::MySql8044))->reserved('_utf8mb4'));
        self::assertTrue((new Keywords(GrammarRelease::MySql8044))->reserved('_x'));
        self::assertTrue((new Keywords(GrammarRelease::MySql5744))->reserved('_'));
    }

    public function testReservedIsFalseForOrdinaryWords(): void
    {
        $keywords = new Keywords(GrammarRelease::MySql8044);

        self::assertFalse($keywords->reserved('foo'));
        self::assertFalse($keywords->reserved('users'));
        self::assertFalse($keywords->reserved('selected'));
        self::assertFalse($keywords->reserved('x_utf8mb4'));
        self::assertFalse($keywords->reserved(''));
    }

    public function testReservedFollowsTheKeywordArtifactOfEachRelease(): void
    {
        self::assertFalse((new Keywords(GrammarRelease::MySql5744))->reserved('rank'));
        self::assertTrue((new Keywords(GrammarRelease::MySql8044))->reserved('rank'));
        self::assertFalse((new Keywords(GrammarRelease::MySql5744))->reserved('cume_dist'));
        self::assertTrue((new Keywords(GrammarRelease::MySql8044))->reserved('CUME_DIST'));
        self::assertFalse((new Keywords(GrammarRelease::MySql5651))->reserved('lateral'));
        self::assertTrue((new Keywords(GrammarRelease::MySql847))->reserved('lateral'));
        self::assertFalse((new Keywords(GrammarRelease::MySql5744))->reserved('json_table'));
        self::assertTrue((new Keywords(GrammarRelease::MySql810))->reserved('json_table'));
        self::assertFalse((new Keywords(GrammarRelease::MySql5744))->reserved('intersect'));
        self::assertTrue((new Keywords(GrammarRelease::MySql8044))->reserved('intersect'));
        self::assertFalse((new Keywords(GrammarRelease::MySql8044))->reserved('qualify'));
        self::assertTrue((new Keywords(GrammarRelease::MySql910))->reserved('qualify'));
        self::assertFalse((new Keywords(GrammarRelease::MySql8044))->reserved('vector'));
        self::assertTrue((new Keywords(GrammarRelease::MySql901))->reserved('vector'));
        self::assertTrue((new Keywords(GrammarRelease::MySql5744))->reserved('select'));
        self::assertTrue((new Keywords(GrammarRelease::MySql5744))->reserved('count'));
    }
}
