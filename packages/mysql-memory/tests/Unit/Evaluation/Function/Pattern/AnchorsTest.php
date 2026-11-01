<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Anchors;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Anchors::class)]
#[Small]
final class AnchorsTest extends TestCase
{
    public function testDotFollowsTheLineFlags(): void
    {
        $translator = new Translator();
        $translator->mode = new Mode(false, true, false, true);

        self::assertSame(['[^\n]', '[^\n\x{B}\f\r\x{85}\x{2028}\x{2029}]'], [$translator->anchors->dot(), (new Translator())->anchors->dot()]);
    }

    public function testCaretMatchesAfterALineTerminatorButAtTheEndInTheMultilineMode(): void
    {
        $translator = new Translator();
        $translator->mode = new Mode(false, true);
        $source = '/' . $translator->anchors->caret() . '/u';

        $count = preg_match_all($source, "x\r\na\n", $found, PREG_OFFSET_CAPTURE);

        self::assertSame([2, 3, 0], [$count, $found[0][1][1] ?? null, preg_match('/x' . $translator->anchors->caret() . '/u', 'x')]);
    }

    public function testDollarMatchesBeforeAFinalLineTerminator(): void
    {
        $source = '/a' . (new Translator())->anchors->dollar() . '/u';

        self::assertSame([1, 1, 0], [preg_match($source, "a\r\n"), preg_match($source, 'a'), preg_match($source, "a\n\n")]);
    }

    public function testEndIgnoresTheUnixLinesMode(): void
    {
        self::assertSame(1, preg_match('/a' . (new Translator())->anchors->end() . '/u', "a\r"));
    }

    public function testBoundaryMatchesBetweenAWordCharacterAndAnother(): void
    {
        $source = '/' . (new Translator())->anchors->boundary(false) . 'b/u';

        self::assertSame([1, 0], [preg_match($source, 'a b'), preg_match($source, 'ab')]);
    }
}
