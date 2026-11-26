<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(IntegerTokenLowering::class)]
#[Small]
final class IntegerTokenLoweringTest extends TestCase
{
    public function testLowerDecodesTheDigitsWithoutNarrowingTheirWidth(): void
    {
        $value = (new IntegerTokenLowering())->lower(new NumberLiteral('009223372036854775807'));

        self::assertSame(['9223372036854775807', 2, false], $value);
    }

    public function testSpellingRetainsEveryLeadingDigit(): void
    {
        $text = str_repeat('0', 68) . '1';

        self::assertSame($text, (new IntegerTokenLowering())->spelling(new NumberLiteral($text)));
    }

    public function testGroupedSpellingRetainsPolarityAndDigitsThroughWrappers(): void
    {
        $digits = new NumberLiteral(str_repeat('0', 68) . '9223372036854775808');
        $plus = new Unary(UnaryOperator::Plus, new Grouped($digits));
        $minus = new Unary(UnaryOperator::Minus, new Grouped($plus));
        $token = new IntegerTokenLowering();

        self::assertSame('-' . $digits->text, $token->groupedSpelling(new Grouped($minus)));
        self::assertNull($token->groupedSpelling(new Unary(UnaryOperator::Minus, new Grouped($minus))));
    }
}
