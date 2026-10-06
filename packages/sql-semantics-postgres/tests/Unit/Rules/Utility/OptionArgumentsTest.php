<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OptionArguments::class)]
#[Small]
final class OptionArgumentsTest extends TestCase
{
    public function testBooleanReadsTheValuesTheServerAccepts(): void
    {
        $values = new OptionArguments();
        self::assertSame(
            [true, true, false, true, false, true, false],
            [
                $values->boolean(new UtilityOption(new Name('verbose'))),
                $values->boolean(new UtilityOption(new Name('verbose'), Toggle::On)),
                $values->boolean(new UtilityOption(new Name('verbose'), Toggle::False)),
                $values->boolean(new UtilityOption(new Name('verbose'), new StringConstant('TRUE'))),
                $values->boolean(new UtilityOption(new Name('verbose'), new Word(new Name('Off')))),
                $values->boolean(new UtilityOption(new Name('verbose'), new SignedNumber(false, new IntegerConstant('1')))),
                $values->boolean(new UtilityOption(new Name('verbose'), new SignedNumber(true, new IntegerConstant('0')))),
            ],
        );
    }

    public function testBooleanRefusesOtherValues(): void
    {
        $values = new OptionArguments();
        self::assertSame(
            [null, null, null, null],
            [
                $values->boolean(new UtilityOption(new Name('verbose'), new StringConstant('yes'))),
                $values->boolean(new UtilityOption(new Name('verbose'), new SignedNumber(false, new IntegerConstant('2')))),
                $values->boolean(new UtilityOption(new Name('verbose'), new SignedNumber(true, new IntegerConstant('1')))),
                $values->boolean(new UtilityOption(new Name('verbose'), new SignedNumber(false, new NumericConstant('1.0')))),
            ],
        );
    }

    public function testIntegerReadsIntegersThatFitIn32Bits(): void
    {
        $values = new OptionArguments();
        self::assertSame(
            [2147483647, -2147483647, null, null, null],
            [
                $values->integer(new UtilityOption(new Name('parallel'), new SignedNumber(false, new IntegerConstant('2147483647')))),
                $values->integer(new UtilityOption(new Name('parallel'), new SignedNumber(true, new IntegerConstant('2147483647')))),
                $values->integer(new UtilityOption(new Name('parallel'), new SignedNumber(false, new NumericConstant('2147483648')))),
                $values->integer(new UtilityOption(new Name('parallel'), new StringConstant('2'))),
                $values->integer(new UtilityOption(new Name('parallel'))),
            ],
        );
    }

    public function testEnabledReadsTheLastOccurrence(): void
    {
        $options = [new UtilityOption(new Name('full')), new UtilityOption(new Name('full'), Toggle::False), new UtilityOption(new Name('freeze'), new StringConstant('maybe'))];
        self::assertSame(
            [false, true, false],
            [(new OptionArguments())->enabled($options, 'full', true), (new OptionArguments())->enabled($options, 'freeze', true), (new OptionArguments())->enabled($options, 'verbose', false)],
        );
    }
}
