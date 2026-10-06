<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericClass;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericContext;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(NumericContext::class)]
#[Small]
final class NumericContextTest extends TestCase
{
    public function testClassifyFollowsTheTypeAndTreatsAHexadecimalLiteralAsAnUnsignedInteger(): void
    {
        $context = new NumericContext();
        $number = new NumberLiteral('1');

        self::assertSame(
            [NumericClass::Unsigned, NumericClass::Signed, NumericClass::Decimal, NumericClass::Double, NumericClass::Double, NumericClass::Unsigned],
            [
                $context->classify($number, new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])),
                $context->classify($number, new Integral(IntegralKind::Int)),
                $context->classify($number, new Decimal()),
                $context->classify($number, new Character(CharacterKind::VarChar)),
                $context->classify($number, null),
                $context->classify(new Grouped(new RadixLiteral(Radix::Hexadecimal, '10')), new Binary(BinaryKind::VarBinary)),
            ],
        );
    }

    public function testTemporalIsAnIntegerWithoutFractionalSeconds(): void
    {
        $context = new NumericContext();

        self::assertSame([NumericClass::Signed, NumericClass::Signed, NumericClass::Decimal], [$context->temporal(new Temporal(TemporalKind::Date)), $context->temporal(new Temporal(TemporalKind::DateTime, '0')), $context->temporal(new Temporal(TemporalKind::Time, '3'))]);
    }

    public function testElementaryClassifiesBoolBitAndJson(): void
    {
        $context = new NumericContext();

        self::assertSame([NumericClass::Signed, NumericClass::Unsigned, NumericClass::Double], [$context->elementary(new Elementary(ElementaryKind::Boolean)), $context->elementary(new Elementary(ElementaryKind::Bit)), $context->elementary(new Elementary(ElementaryKind::Json))]);
    }
}
