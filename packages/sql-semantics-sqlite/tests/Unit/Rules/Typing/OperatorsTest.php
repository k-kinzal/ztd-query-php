<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Operators;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Operators::class)]
#[Small]
final class OperatorsTest extends TestCase
{
    public function testBinaryIsFamilyIsAnIntegerThatIsNeverNull(): void
    {
        $operators = new Operators();
        $null = new ScalarFact(new NullOnly(), Nullability::Nullable);
        $dependent = new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent);
        $facts = [
            $operators->binary(BinaryOperator::Is, $null, $null),
            $operators->binary(BinaryOperator::IsNot, $null, $dependent),
            $operators->binary(BinaryOperator::IsDistinctFrom, $dependent, $null),
            $operators->binary(BinaryOperator::IsNotDistinctFrom, $dependent, $dependent),
        ];

        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull], array_map(static fn (ScalarFact $fact): Nullability => $fact->nullability, $facts));
        self::assertInstanceOf(Known::class, $facts[0]->type);
        self::assertSame(Storage::Integer, $facts[0]->type->descriptor);
        self::assertInstanceOf(Known::class, $facts[3]->type);
        self::assertSame(Storage::Integer, $facts[3]->type->descriptor);
    }

    public function testBinaryComparisonsAndBitwiseOperatorsAreIntegerAndNullWhenAnOperandCanBe(): void
    {
        $operators = new Operators();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $nullable = new ScalarFact(new Known(Storage::Text), Nullability::Nullable);
        $equal = $operators->binary(BinaryOperator::Equal, $integer, $nullable);
        $less = $operators->binary(BinaryOperator::Less, $integer, $integer);
        $and = $operators->binary(BinaryOperator::BitAnd, $integer, new ScalarFact(new NullOnly(), Nullability::Nullable));
        $shift = $operators->binary(BinaryOperator::ShiftLeft, new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent), $integer);

        self::assertInstanceOf(Known::class, $equal->type);
        self::assertSame(Storage::Integer, $equal->type->descriptor);
        self::assertSame(Nullability::Nullable, $equal->nullability);
        self::assertSame(Nullability::NotNull, $less->nullability);
        self::assertInstanceOf(NullOnly::class, $and->type);
        self::assertInstanceOf(Known::class, $shift->type);
        self::assertSame(Storage::Integer, $shift->type->descriptor);
        self::assertSame(Nullability::Dependent, $shift->nullability);
    }

    public function testBinaryLogicalOperatorsAreIntegerEvenWithANullOperand(): void
    {
        $operators = new Operators();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $and = $operators->binary(BinaryOperator::And, new ScalarFact(new NullOnly(), Nullability::Nullable), $integer);
        $or = $operators->binary(BinaryOperator::Or, $integer, $integer);

        self::assertInstanceOf(Known::class, $and->type);
        self::assertSame(Storage::Integer, $and->type->descriptor);
        self::assertSame(Nullability::Nullable, $and->nullability);
        self::assertInstanceOf(Known::class, $or->type);
        self::assertSame(Nullability::NotNull, $or->nullability);
    }

    public function testBinaryArithmeticIsIntegerOrRealUnlessAnOperandIsCertainlyRealOrNull(): void
    {
        $operators = new Operators();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $real = new ScalarFact(new Known(Storage::Real), Nullability::NotNull);
        $sum = $operators->binary(BinaryOperator::Add, $integer, $integer);
        $difference = $operators->binary(BinaryOperator::Subtract, $real, $integer);
        $product = $operators->binary(BinaryOperator::Multiply, new ScalarFact(new NullOnly(), Nullability::Nullable), $integer);
        $open = $operators->binary(BinaryOperator::Add, new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent), $integer);

        self::assertInstanceOf(Choice::class, $sum->type);
        self::assertSame([Storage::Integer, Storage::Real], $sum->type->alternatives);
        self::assertSame(Nullability::NotNull, $sum->nullability);
        self::assertInstanceOf(Known::class, $difference->type);
        self::assertSame(Storage::Real, $difference->type->descriptor);
        self::assertInstanceOf(NullOnly::class, $product->type);
        self::assertSame(Nullability::Nullable, $product->nullability);
        self::assertInstanceOf(Choice::class, $open->type);
        self::assertSame(Nullability::Dependent, $open->nullability);
    }

    public function testBinaryDivisionAndRemainderCanAlwaysBeNull(): void
    {
        $operators = new Operators();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $quotient = $operators->binary(BinaryOperator::Divide, $integer, $integer);
        $remainder = $operators->binary(BinaryOperator::Modulo, $integer, $integer);
        $real = $operators->binary(BinaryOperator::Divide, new ScalarFact(new Known(Storage::Real), Nullability::NotNull), $integer);

        self::assertInstanceOf(Choice::class, $quotient->type);
        self::assertSame([Storage::Integer, Storage::Real], $quotient->type->alternatives);
        self::assertSame(Nullability::Nullable, $quotient->nullability);
        self::assertSame(Nullability::Nullable, $remainder->nullability);
        self::assertInstanceOf(Known::class, $real->type);
        self::assertSame(Storage::Real, $real->type->descriptor);
        self::assertSame(Nullability::Nullable, $real->nullability);
    }

    public function testBinaryConcatenationIsTextUnlessAnOperandIsNull(): void
    {
        $operators = new Operators();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $text = $operators->binary(BinaryOperator::Concat, $integer, new ScalarFact(new Known(Storage::Blob), Nullability::Nullable));
        $null = $operators->binary(BinaryOperator::Concat, $integer, new ScalarFact(new NullOnly(), Nullability::Nullable));

        self::assertInstanceOf(Known::class, $text->type);
        self::assertSame(Storage::Text, $text->type->descriptor);
        self::assertSame(Nullability::Nullable, $text->nullability);
        self::assertInstanceOf(NullOnly::class, $null->type);
    }

    public function testBinaryJsonExtractionYieldsNullableTextOrAValue(): void
    {
        $operators = new Operators();
        $text = new ScalarFact(new Known(Storage::Text), Nullability::NotNull);
        $null = new ScalarFact(new NullOnly(), Nullability::Nullable);
        $extract = $operators->binary(BinaryOperator::Extract, $text, $text);
        $value = $operators->binary(BinaryOperator::ExtractValue, $text, $text);

        self::assertInstanceOf(Known::class, $extract->type);
        self::assertSame(Storage::Text, $extract->type->descriptor);
        self::assertSame(Nullability::Nullable, $extract->nullability);
        self::assertInstanceOf(Choice::class, $value->type);
        self::assertSame([Storage::Integer, Storage::Real, Storage::Text], $value->type->alternatives);
        self::assertSame(Nullability::Nullable, $value->nullability);
        self::assertInstanceOf(NullOnly::class, $operators->binary(BinaryOperator::Extract, $null, $text)->type);
        self::assertInstanceOf(NullOnly::class, $operators->binary(BinaryOperator::ExtractValue, $text, $null)->type);
    }

    public function testUnaryPlusKeepsTheTypeAndTheNullability(): void
    {
        $type = new Known(Storage::Text);
        $fact = (new Operators())->unary(UnaryOperator::Plus, new ScalarFact($type, Nullability::Nullable));

        self::assertSame($type, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertNull($fact->resolution);
    }

    public function testUnaryMinusFollowsTheArithmeticRule(): void
    {
        $operators = new Operators();
        $integer = $operators->unary(UnaryOperator::Minus, new ScalarFact(new Known(Storage::Integer), Nullability::NotNull));
        $real = $operators->unary(UnaryOperator::Minus, new ScalarFact(new Known(Storage::Real), Nullability::NotNull));
        $text = $operators->unary(UnaryOperator::Minus, new ScalarFact(new Known(Storage::Text), Nullability::Nullable));
        $null = $operators->unary(UnaryOperator::Minus, new ScalarFact(new NullOnly(), Nullability::Nullable));

        self::assertInstanceOf(Choice::class, $integer->type);
        self::assertSame([Storage::Integer, Storage::Real], $integer->type->alternatives);
        self::assertSame(Nullability::NotNull, $integer->nullability);
        self::assertInstanceOf(Known::class, $real->type);
        self::assertSame(Storage::Real, $real->type->descriptor);
        self::assertInstanceOf(Choice::class, $text->type);
        self::assertSame(Nullability::Nullable, $text->nullability);
        self::assertInstanceOf(NullOnly::class, $null->type);
    }

    public function testUnaryNotAndBitNotAreIntegerUnlessTheOperandIsNull(): void
    {
        $operators = new Operators();
        $not = $operators->unary(UnaryOperator::Not, new ScalarFact(new Known(Storage::Text), Nullability::NotNull));
        $bitNot = $operators->unary(UnaryOperator::BitNot, new ScalarFact(new Known(Storage::Integer), Nullability::Nullable));
        $null = $operators->unary(UnaryOperator::Not, new ScalarFact(new NullOnly(), Nullability::Nullable));

        self::assertInstanceOf(Known::class, $not->type);
        self::assertSame(Storage::Integer, $not->type->descriptor);
        self::assertSame(Nullability::NotNull, $not->nullability);
        self::assertInstanceOf(Known::class, $bitNot->type);
        self::assertSame(Storage::Integer, $bitNot->type->descriptor);
        self::assertSame(Nullability::Nullable, $bitNot->nullability);
        self::assertInstanceOf(NullOnly::class, $null->type);
    }
}
