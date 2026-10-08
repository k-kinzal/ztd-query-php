<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Grouping;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Scanner;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Grouping::class)]
#[Small]
final class GroupingTest extends TestCase
{
    public function testGroupRefusesAnUnboundedLookbehind(): void
    {
        $this->expectExceptionMessage('The look-behind assertion exceeds the limit in regular expression.');

        (new Translator())->translate('(?<=a+)b', new Mode());
    }

    public function testNameRefusesATakenName(): void
    {
        $this->expectExceptionMessage('A capture group has an invalid name.');

        (new Translator())->translate('(?<n>a)(?<n>b)', new Mode());
    }

    public function testCommentRefusesAnUnclosedComment(): void
    {
        $this->expectExceptionMessage('Mismatched parenthesis in regular expression.');

        (new Translator())->translate('(?#a', new Mode());
    }

    public function testFlagsRefusesAnUnknownFlagAfterAKnownOne(): void
    {
        $this->expectExceptionMessage('Invalid match mode flag in regular expression.');

        (new Translator())->translate('(?iz)a', new Mode());
    }

    public function testCloseReadsTheBodyAndRestoresTheMode(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('a|b)c');
        $translator->mode = new Mode(true);
        $fragment = $translator->grouping->close('(?:', new Mode())[0];

        self::assertSame(['(?:a|b)', 1, 1, false, 'c'], [$fragment->source, $fragment->minimum, $fragment->maximum, $translator->mode->caseless, $translator->scanner->peek()]);
    }

    public function testCaptureNumbersTheNamedGroup(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('n>a)');

        self::assertSame(['(a)', ['n' => 1]], [$translator->grouping->capture(new Mode())[0]->source, $translator->names]);
    }

    public function testFlaggedScopesTheGroupOrTheRestOfTheEnclosingOne(): void
    {
        $rest = new Translator();
        $rest->scanner = Scanner::of(')');
        $scoped = new Translator();
        $scoped->scanner = Scanner::of(':a)');

        self::assertSame(['(?i)', '(?i:a)', true, false], [$rest->grouping->flagged('i', new Mode())[0]->source, $scoped->grouping->flagged('i', new Mode())[0]->source, $rest->mode->caseless, $scoped->mode->caseless]);
    }
}
