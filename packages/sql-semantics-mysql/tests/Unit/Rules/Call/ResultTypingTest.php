<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(ResultTyping::class)]
#[Small]
final class ResultTypingTest extends TestCase
{
    public function testFactCombinesTheTypeAndTheNullRule(): void
    {
        $text = new ScalarFact(new Known(TypeClass::Character->descriptor()), Nullability::NotNull);
        $fact = (new ResultTyping())->fact('SP', [$text, $text]);

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testFactRejectsAMalformedCode(): void
    {
        $this->expectExceptionMessage('A result code has two letters: S');

        (new ResultTyping())->fact('S', []);
    }

    public function testTypeAnswersFixedAndDerivedTypes(): void
    {
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $double = new ScalarFact(new Known(TypeClass::Floating->descriptor()), Nullability::NotNull);
        $typing = new ResultTyping();

        self::assertEquals(new Known(TypeClass::Json->descriptor()), $typing->type('J', []));
        self::assertEquals(new Known(TypeClass::Integer->descriptor()), $typing->type('H', [$int, $double]));
        self::assertEquals(new Known(TypeClass::Floating->descriptor()), $typing->type('O', [$int, $double]));
        self::assertSame($double->type, $typing->type('2', [$int, $double]));
        self::assertEquals(new Choice([TypeClass::Date->descriptor(), TypeClass::Time->descriptor(), TypeClass::DateTime->descriptor()]), $typing->type('K', []));
    }

    public function testTypeRejectsAnUnknownCode(): void
    {
        $this->expectExceptionMessage('Unknown result type code: Q');

        (new ResultTyping())->type('Q', []);
    }

    public function testStringFollowsTheCharacterSetOfTheArguments(): void
    {
        $missing = new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('f')))]);
        $typing = new ResultTyping();

        self::assertEquals(new Known(TypeClass::Binary->descriptor()), $typing->string([new Known(TypeClass::Character->descriptor()), new Known(TypeClass::Binary->descriptor())]));
        self::assertEquals(new Known(TypeClass::Character->descriptor()), $typing->string([new Known(TypeClass::Integer->descriptor()), new NullOnly()]));
        self::assertEquals(new Choice([TypeClass::Character->descriptor(), TypeClass::Binary->descriptor()]), $typing->string([$missing]));
    }

    public function testNumericConvertsArgumentsToNumbers(): void
    {
        $typing = new ResultTyping();

        self::assertEquals(new Known(TypeClass::Decimal->descriptor()), $typing->numeric([new Known(TypeClass::Decimal->descriptor())], false));
        self::assertEquals(new Choice([TypeClass::Integer->descriptor(), TypeClass::Decimal->descriptor()]), $typing->numeric([new Known(TypeClass::Decimal->descriptor())], true));
        self::assertEquals(new Known(TypeClass::Floating->descriptor()), $typing->numeric([new Known(TypeClass::Character->descriptor())], false));
        self::assertEquals(new NullOnly(), $typing->numeric([new NullOnly()], false));
    }

    public function testNumbersAnswersTheNumericClassesOfAnArgument(): void
    {
        self::assertSame([TypeClass::Integer, TypeClass::Decimal, TypeClass::Floating], (new ResultTyping())->numbers([], false));
        self::assertSame([TypeClass::Integer, TypeClass::Decimal], (new ResultTyping())->numbers([TypeClass::Date], false));
    }

    public function testTemporalKeepsATimeOrADatetime(): void
    {
        $typing = new ResultTyping();

        self::assertEquals(new Known(TypeClass::Time->descriptor()), $typing->temporal(new Known(TypeClass::Time->descriptor())));
        self::assertEquals(new Known(TypeClass::Character->descriptor()), $typing->temporal(new Known(TypeClass::Integer->descriptor())));
        self::assertInstanceOf(Choice::class, $typing->temporal(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('f')))])));
    }

    public function testDateArithmeticFollowsTheDateAndTheUnit(): void
    {
        $typing = new ResultTyping();
        $date = new Known(TypeClass::Date->descriptor());

        self::assertEquals($date, $typing->dateArithmetic($date, IntervalUnit::Month));
        self::assertEquals(new Known(TypeClass::DateTime->descriptor()), $typing->dateArithmetic($date, IntervalUnit::Hour));
        self::assertEquals(new Known(TypeClass::Time->descriptor()), $typing->dateArithmetic(new Known(TypeClass::Time->descriptor()), IntervalUnit::MinuteSecond));
        self::assertEquals(new Known(TypeClass::Character->descriptor()), $typing->dateArithmetic(new Known(TypeClass::Character->descriptor()), IntervalUnit::Day));
    }

    public function testNullabilityAppliesTheNullRules(): void
    {
        $nullable = new ScalarFact(new NullOnly(), Nullability::Nullable);
        $notNull = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $typing = new ResultTyping();

        self::assertSame(Nullability::Nullable, $typing->nullability('P', [$notNull, $nullable]));
        self::assertSame(Nullability::NotNull, $typing->nullability('C', [$nullable, $notNull]));
        self::assertSame(Nullability::NotNull, $typing->nullability('F', [$notNull, $nullable]));
        self::assertSame(Nullability::Nullable, $typing->nullability('L', [$notNull, $nullable]));
        self::assertSame(Nullability::NotNull, $typing->nullability('R', [$nullable, $notNull]));
        self::assertSame(Nullability::Nullable, $typing->nullability('Y', []));
    }

    public function testNullabilityRejectsAnUnknownRule(): void
    {
        $this->expectExceptionMessage('Unknown NULL rule code: Q');

        (new ResultTyping())->nullability('Q', []);
    }

    public function testPropagateIsNullWhenAnOperandCanBe(): void
    {
        self::assertSame(Nullability::Dependent, (new ResultTyping())->propagate([Nullability::NotNull, Nullability::Dependent]));
        self::assertSame(Nullability::NotNull, (new ResultTyping())->propagate([]));
    }

    public function testCoalesceIsNullOnlyWhenEveryOperandCanBe(): void
    {
        self::assertSame(Nullability::Dependent, (new ResultTyping())->coalesce([Nullability::Nullable, Nullability::Dependent]));
        self::assertSame(Nullability::Nullable, (new ResultTyping())->coalesce([Nullability::Nullable]));
    }
}
