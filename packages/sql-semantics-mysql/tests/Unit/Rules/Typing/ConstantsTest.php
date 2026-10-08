<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Constants;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(Constants::class)]
#[Small]
final class ConstantsTest extends TestCase
{
    public function testNegativeHoldsForANegativeIntegerConstantOtherThanALiteral(): void
    {
        $constants = new Constants();

        self::assertTrue($constants->negative(new Unary(UnaryOperator::Minus, new NumberLiteral('3'))));
        self::assertTrue($constants->negative(new Grouped(new Arithmetic(ArithmeticOperator::Minus, new NumberLiteral('1'), new NumberLiteral('4')))));
        self::assertTrue($constants->negative(new Cast(new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new CastTarget(CastKind::Unsigned))));
        self::assertFalse($constants->negative(new Grouped(new NumberLiteral('9223372036854775808'))));
        self::assertFalse($constants->negative(new Unary(UnaryOperator::Minus, new NumberLiteral('0'))));
        self::assertFalse($constants->negative(new Unary(UnaryOperator::Minus, new StringLiteral(['3']))));
    }

    public function testValueEvaluatesLiteralsAndOperators(): void
    {
        $constants = new Constants();

        self::assertSame([1, false], $constants->value(new BooleanLiteral(true)));
        self::assertSame([-3, false], $constants->value(new SignedLiteral(true, new NumberLiteral('3'))));
        self::assertSame([-1, true], $constants->value(new Unary(UnaryOperator::Invert, new NumberLiteral('0'))));
        self::assertSame([3, false], $constants->value(new Unary(UnaryOperator::Plus, new NumberLiteral('3'))));
        self::assertSame([0, false], $constants->value(new Unary(UnaryOperator::Not, new NumberLiteral('3'))));
        self::assertNull($constants->value(new Unary(UnaryOperator::Minus, new Unary(UnaryOperator::Minus, new NumberLiteral('3')))));
        self::assertSame([PHP_INT_MIN, false], $constants->value(new Unary(UnaryOperator::Minus, new Grouped(new NumberLiteral('9223372036854775808')))));
        self::assertNull($constants->value(new NumberLiteral('1.5')));
        self::assertNull($constants->value(new StringLiteral(['3'])));
    }

    public function testLiteralReadsAnUnsignedLiteralAsItsBits(): void
    {
        $constants = new Constants();

        self::assertSame([-1, true], $constants->literal(new NumberLiteral('18446744073709551615'), false));
        self::assertSame([PHP_INT_MIN, true], $constants->literal(new NumberLiteral('9223372036854775808'), false));
        self::assertNull($constants->literal(new NumberLiteral('18446744073709551616'), false));
        self::assertNull($constants->literal(new NumberLiteral('9223372036854775809'), true));
    }

    public function testAboveCountsFromTheSignedLimit(): void
    {
        self::assertSame([0, 1, PHP_INT_MAX, 776627963145224192], [(new Constants())->above('9223372036854775808'), (new Constants())->above('9223372036854775809'), (new Constants())->above('18446744073709551615'), (new Constants())->above('10000000000000000000')]);
    }

    public function testUnaryNegatesOnlyAValueThatStaysAnInteger(): void
    {
        self::assertSame([-3, false], (new Constants())->unary(new Unary(UnaryOperator::Minus, new Cast(new NumberLiteral('3'), new CastTarget(CastKind::Unsigned)))));
        self::assertNull((new Constants())->unary(new Unary(UnaryOperator::Minus, new Cast(new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new CastTarget(CastKind::Signed)))));
    }

    public function testArithmeticFollowsBigintArithmetic(): void
    {
        $constants = new Constants();

        self::assertSame([-2, false], $constants->arithmetic(ArithmeticOperator::Modulo, [-7, false], [5, false]));
        self::assertSame([-2, false], $constants->arithmetic(ArithmeticOperator::IntegerDivide, [-7, false], [3, false]));
        self::assertSame([-3, true], $constants->arithmetic(ArithmeticOperator::BitOr, [-3, false], [0, false]));
        self::assertSame([PHP_INT_MIN, true], $constants->arithmetic(ArithmeticOperator::ShiftLeft, [1, false], [63, false]));
        self::assertSame([PHP_INT_MAX >> 1, true], $constants->arithmetic(ArithmeticOperator::ShiftRight, [-1, false], [2, false]));
        self::assertSame([0, true], $constants->arithmetic(ArithmeticOperator::ShiftRight, [-1, false], [64, false]));
        self::assertNull($constants->arithmetic(ArithmeticOperator::Plus, [PHP_INT_MAX, false], [1, false]));
        self::assertNull($constants->arithmetic(ArithmeticOperator::Minus, [3, true], [4, false]));
        self::assertNull($constants->arithmetic(ArithmeticOperator::IntegerDivide, [3, false], [0, false]));
        self::assertNull($constants->arithmetic(ArithmeticOperator::Divide, [3, false], [1, false]));
        self::assertNull($constants->arithmetic(ArithmeticOperator::Plus, null, [1, false]));
        self::assertSame([PHP_INT_MIN + 2, true], $constants->arithmetic(ArithmeticOperator::Plus, [1, false], [PHP_INT_MIN + 1, true]));
    }

    public function testBeyondKeepsAnUnsignedValueAboveTheSignedLimit(): void
    {
        $constants = new Constants();

        self::assertSame([PHP_INT_MIN, true], $constants->beyond(ArithmeticOperator::Minus, [PHP_INT_MIN + 1, true], [1, false]));
        self::assertSame([PHP_INT_MIN, true], $constants->beyond(ArithmeticOperator::Plus, [-1, false], [PHP_INT_MIN + 1, true]));
        self::assertNull($constants->beyond(ArithmeticOperator::Minus, [PHP_INT_MIN, true], [1, false]));
        self::assertNull($constants->beyond(ArithmeticOperator::Plus, [-1, true], [1, false]));
        self::assertNull($constants->beyond(ArithmeticOperator::Minus, [1, false], [-1, true]));
        self::assertNull($constants->beyond(ArithmeticOperator::Multiply, [-1, true], [1, false]));
    }

    public function testCastReadsTheBitsAsTheTargetSays(): void
    {
        self::assertSame([-3, true], (new Constants())->cast(new Cast(new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new CastTarget(CastKind::Unsigned))));
        self::assertSame([-3, false], (new Constants())->cast(new Cast(new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new CastTarget(CastKind::Signed))));
        self::assertNull((new Constants())->cast(new Cast(new NumberLiteral('3'), new CastTarget(CastKind::Char))));
    }

    public function testSubqueryEvaluatesASingleConstantWithoutATable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $outer = $semantics->analyze('SELECT (SELECT -3), (SELECT -3 FROM t), (SELECT -3, 1)')->statement;

        self::assertInstanceOf(Select::class, $outer);
        self::assertInstanceOf(SelectExpression::class, $outer->items[0]);
        self::assertInstanceOf(SelectExpression::class, $outer->items[1]);
        self::assertInstanceOf(ScalarSubquery::class, $outer->items[0]->expression);
        self::assertInstanceOf(ScalarSubquery::class, $outer->items[1]->expression);
        self::assertSame([-3, false], (new Constants())->subquery($outer->items[0]->expression));
        self::assertNull((new Constants())->subquery($outer->items[1]->expression));
    }
}
