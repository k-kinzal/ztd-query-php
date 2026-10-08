<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Fragment;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Scanner;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Translator::class)]
#[Small]
final class TranslatorTest extends TestCase
{
    public function testCompileTranslatesOnceAndRaisesACachedErrorAgain(): void
    {
        $first = Translator::compile('a(b)', new Mode());

        self::assertSame($first, Translator::compile('a(b)', new Mode()));
        $this->expectExceptionMessage('Syntax error in regular expression on line 1, character 3.');

        Translator::compile('a**', new Mode());
    }

    public function testTranslateCountsTheGroupsAndNames(): void
    {
        $expression = (new Translator())->translate('(a)(?:b)(?<n>c)(?>d)', new Mode(true));

        self::assertSame(['/(?i)(a)(?:b)(c)(?>d)/u', 2, ['n' => 2]], [$expression->source, $expression->groups, $expression->names]);
    }

    public function testTranslateRefusesAnEmptyPatternAndAnUnmatchedParenthesis(): void
    {
        $this->expectExceptionMessage('Mismatched parenthesis in regular expression.');

        (new Translator())->translate('a)', new Mode());
    }

    public function testTranslateWritesBackReferencesOnceEveryGroupIsKnown(): void
    {
        self::assertSame(['/(?:\g{2})(a)(b)/u', '/(a)(?:\g{1})1/u'], [(new Translator())->translate('\2(a)(b)', new Mode())->source, (new Translator())->translate('(a)\11', new Mode())->source]);
    }

    public function testAlternationReadsTheBranches(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('ab|c');

        self::assertSame('ab|c', $translator->alternation()->source);
    }

    public function testSequenceReadsTermsUpToABar(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('a\Qb*\E+|c');

        self::assertSame('ab(?:\x{2A})+', $translator->sequence()->source);
    }

    public function testSkipIgnoresSpaceAndCommentsInTheXMode(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of(" # note\n a");
        $translator->mode = new Mode(false, false, false, false, true);
        $translator->skip();

        self::assertSame('a', $translator->scanner->peek());
    }

    public function testSpaceKnowsPatternWhiteSpace(): void
    {
        self::assertSame([true, true, false], [(new Translator())->space(' '), (new Translator())->space("\u{2028}"), (new Translator())->space("\u{3000}")]);
    }

    public function testLiteralAlsoMatchesTheFullCaseFoldingWithoutRegardToCase(): void
    {
        $translator = new Translator();
        $translator->mode = new Mode(true);

        self::assertSame(['(?:\x{DF}|ss)', 'a'], [$translator->literal('ß')->source, (new Translator())->literal('a')->source]);
    }

    public function testFoldedLetsLettersThatSpellAFoldingMatchItsCharacter(): void
    {
        $translator = new Translator();
        $translator->mode = new Mode(true);
        $parts = $translator->folded([$translator->literal('S'), $translator->literal('s'), $translator->literal('e')]);

        self::assertSame(1, preg_match('/(?i)\A' . Fragment::sequence($parts)->source . '\z/u', 'ße'));
    }

    public function testExpansionsListsTheCharactersWhoseFoldingIsSeveral(): void
    {
        self::assertContains('ß', Translator::expansions()['ss']);
    }

    public function testAtomRefusesAQuantifierWithNothingBefore(): void
    {
        $this->expectExceptionMessage('Syntax error in regular expression on line 1, character 1.');

        (new Translator())->translate('{', new Mode());
    }

    public function testResolveRefusesANameNoGroupHas(): void
    {
        $this->expectExceptionMessage('A capture group has an invalid name.');

        (new Translator())->translate('(?<n>a)\k<m>', new Mode());
    }
}
