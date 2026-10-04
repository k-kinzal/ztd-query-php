<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NumberLiteral::class)]
#[Medium]
final class NumberLiteralTest extends TestCase
{
    public function testBeyondSignedTellsAnIntegerAboveTheSignedLimit(): void
    {
        self::assertFalse((new NumberLiteral('9223372036854775807'))->beyondSigned());
        self::assertTrue((new NumberLiteral('9223372036854775808'))->beyondSigned());
        self::assertTrue((new NumberLiteral('18446744073709551615'))->beyondSigned());
    }

    public function testBeyondSignedIgnoresLeadingZeros(): void
    {
        self::assertFalse((new NumberLiteral('09223372036854775807'))->beyondSigned());
        self::assertTrue((new NumberLiteral('0009223372036854775808'))->beyondSigned());
    }

    public function testBeyondSignedIsFalseOutsideTheIntegerForm(): void
    {
        self::assertFalse((new NumberLiteral('18446744073709551616'))->beyondSigned());
        self::assertFalse((new NumberLiteral('9223372036854775808.0'))->beyondSigned());
        self::assertFalse((new NumberLiteral('1e30'))->beyondSigned());
    }

    public function testTypeAnswersBigIntForAnInteger(): void
    {
        $literal = new NumberLiteral('007');
        $type = $literal->type();

        self::assertSame(NumberForm::Integer, $literal->form);
        self::assertInstanceOf(Integral::class, $type);
        self::assertSame(IntegralKind::BigInt, $type->kind);
        self::assertNull($type->width);
        self::assertSame([], $type->modifiers);
    }

    public function testTypeAnswersUnsignedBigIntBeyondTheSignedLimit(): void
    {
        $type = (new NumberLiteral('9223372036854775808'))->type();

        self::assertInstanceOf(Integral::class, $type);
        self::assertSame(IntegralKind::BigInt, $type->kind);
        self::assertSame([NumericModifier::Unsigned], $type->modifiers);
        self::assertTrue($type->unsigned());
    }

    public function testTypeAnswersDecimalForADecimalPointOrBeyondTheUnsignedLimit(): void
    {
        $decimal = (new NumberLiteral('1.50'))->type();
        $huge = (new NumberLiteral('18446744073709551616'))->type();

        self::assertInstanceOf(Decimal::class, $decimal);
        self::assertNull($decimal->precision);
        self::assertNull($decimal->scale);
        self::assertInstanceOf(Decimal::class, $huge);
    }

    public function testTypeAnswersDoubleForAnExponent(): void
    {
        $type = (new NumberLiteral('1.5E-3'))->type();

        self::assertInstanceOf(Floating::class, $type);
        self::assertSame(FloatingKind::Double, $type->kind);
    }

    public function testDeriveScalarAnswersTheTypeOfTheTextThatIsNeverNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = 18446744073709551615');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(NumberLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame('18446744073709551615', $literal->text);
        self::assertSame(NumberForm::Integer, $literal->form);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Integral::class, $fact->type->descriptor);
        self::assertTrue($fact->type->descriptor->unsigned());
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarAnswersDecimalForAnExactNumberWithAPoint(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT .5');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Decimal::class, $fact->type->descriptor);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRenderWritesTheExactText(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT 007', $semantics->analyze('SELECT 007')->toString());
        self::assertSame('SELECT 1.50', $semantics->analyze('SELECT 1.50')->toString());
        self::assertSame('SELECT 1.5E-3', $semantics->analyze('SELECT 1.5E-3')->toString());
        self::assertSame('SELECT .5', $semantics->analyze('SELECT .5')->toString());
    }

    public function testRejectsASignedNumber(): void
    {
        $this->expectExceptionMessage('A number literal is an unsigned integer, decimal or floating-point number.');

        new NumberLiteral('-1');
    }

    public function testRejectsAnIncompleteExponent(): void
    {
        $this->expectExceptionMessage('A number literal is an unsigned integer, decimal or floating-point number.');

        new NumberLiteral('1e');
    }

    public function testRejectsEmptyText(): void
    {
        $this->expectExceptionMessage('A number literal is an unsigned integer, decimal or floating-point number.');

        new NumberLiteral('');
    }
}
