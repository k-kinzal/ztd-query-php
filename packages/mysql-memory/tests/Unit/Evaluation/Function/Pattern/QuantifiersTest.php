<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Fragment;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Quantifiers;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Quantifiers::class)]
#[Small]
final class QuantifiersTest extends TestCase
{
    public function testQuantifiedRefusesAQuantifierAfterAQuantifier(): void
    {
        $this->expectExceptionMessage('Syntax error in regular expression on line 1, character 7.');

        (new Translator())->translate('a{1,2}{3}', new Mode());
    }

    public function testIntervalRefusesAMaximumBelowTheMinimum(): void
    {
        $this->expectExceptionMessage('The maximum is less than the minumum in a {min,max} interval.');

        (new Translator())->translate('a{2,1}', new Mode());
    }

    public function testNumberRefusesANumberAbove16777215(): void
    {
        $this->expectExceptionMessage('Decimal number in regular expression is too large.');

        (new Translator())->translate('a{16777216}', new Mode());
    }

    public function testRepeatWritesTheQuantifier(): void
    {
        $fragment = (new Translator())->quantifiers->repeat(new Fragment('a'), 2, 5, '?');

        self::assertSame(['(?:a){2,5}?', 2, 5], [$fragment->source, $fragment->minimum, $fragment->maximum]);
    }
}
