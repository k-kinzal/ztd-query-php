<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rendering\Keywords;

#[CoversClass(Keywords::class)]
#[Small]
final class KeywordsTest extends TestCase
{
    public function testReservedTellsAKeywordWithoutRegardToAsciiCase(): void
    {
        $keywords = new Keywords();

        self::assertTrue($keywords->reserved('SELECT'));
        self::assertTrue($keywords->reserved('select'));
        self::assertTrue($keywords->reserved('Temp'));
        self::assertTrue($keywords->reserved('abort'));
    }

    public function testReservedTreatsAFallbackKeywordAsUnusableForABareName(): void
    {
        $keywords = new Keywords();

        self::assertTrue($keywords->reserved('key'));
        self::assertTrue($keywords->reserved('if'));
        self::assertTrue($keywords->reserved('filter'));
        self::assertTrue($keywords->reserved('window'));
        self::assertTrue($keywords->reserved('first'));
        self::assertTrue($keywords->reserved('generated'));
    }

    public function testReservedAnswersFalseForAWordTheTokenizerReadsAsAnIdentifier(): void
    {
        $keywords = new Keywords();

        self::assertFalse($keywords->reserved('rowid'));
        self::assertFalse($keywords->reserved('true'));
        self::assertFalse($keywords->reserved('text'));
        self::assertFalse($keywords->reserved('strict'));
        self::assertFalse($keywords->reserved('nocase'));
        self::assertFalse($keywords->reserved(''));
    }
}
