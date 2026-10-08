<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Brackets;
use MySqlMemory\Evaluation\Function\Pattern\Members;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Scanner;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Brackets::class)]
#[Small]
final class BracketsTest extends TestCase
{
    public function testSetReadsRangesNestedSetsAndOperators(): void
    {
        $difference = (new Translator())->translate('[[a-z]--[aeiou]]', new Mode())->source;
        $intersection = (new Translator())->translate('[a-z&&[^b-z]]', new Mode())->source;

        self::assertSame([1, 0, 1, 0], [preg_match($difference, 'b'), preg_match($difference, 'e'), preg_match($intersection, 'a'), preg_match($intersection, 'b')]);
    }

    public function testMemberReadsAPosixProperty(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('[:^alpha:]');
        $source = '/\A' . $translator->brackets->member()[0]->source() . '\z/u';

        self::assertSame([1, 0], [preg_match($source, '1'), preg_match($source, 'a')]);
    }

    public function testCombineAppliesTheOperator(): void
    {
        $translator = new Translator();
        $a = Members::character('a');
        $b = Members::character('b');

        self::assertSame([$b, $a->intersect($b)->source(), $a->minus($b)->source()], [$translator->brackets->combine(null, null, $b), $translator->brackets->combine($a, '&&', $b)->source(), $translator->brackets->combine($a, '--', $b)->source()]);
    }

    public function testBlankSkipsWhiteSpaceInTheXMode(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of("  \ta");
        $translator->mode = new Mode(false, false, false, false, true);
        $translator->brackets->blank();

        self::assertSame('a', $translator->scanner->peek());
    }

    public function testRangeReadsTheEndAfterTheHyphen(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('-\x7A]');
        $source = '/\A' . $translator->brackets->range('a')->source() . '\z/u';

        self::assertSame([1, 0, ']'], [preg_match($source, 'm'), preg_match($source, 'A'), $translator->scanner->peek()]);
    }

    public function testRangeRefusesAnEndBeforeTheStart(): void
    {
        $this->expectExceptionMessage('The regular expression contains an [x-y] character range where x comes after y.');

        (new Translator())->translate('[z-a]', new Mode());
    }
}
