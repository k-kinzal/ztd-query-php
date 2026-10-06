<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Modifiers::class)]
#[Small]
final class ModifiersTest extends TestCase
{
    public function testApplyAnswersTheCatalogTypeWithoutModifiers(): void
    {
        self::assertEquals(new Known(Builtin::Numeric), (new Modifiers())->apply(Builtin::Numeric, []));
    }

    public function testApplyReadsIntegerConstants(): void
    {
        $fact = (new Modifiers())->apply(Builtin::Numeric, [new Constant(new IntegerConstant('10')), new Constant(new StringConstant(' 2 '))]);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('numeric(10,2)', $fact->descriptor->name());
    }

    public function testApplyReportsAModifierThatIsNotAnIntegerConstant(): void
    {
        $fact = (new Modifiers())->apply(Builtin::Numeric, [new NullLiteral()]);
        self::assertInstanceOf(Invalid::class, $fact);
        self::assertSame('Invalid type modifier for type numeric.', $fact->cause->message());
    }

    public function testConstrainedChecksTheRangeEachTypeAccepts(): void
    {
        self::assertInstanceOf(Known::class, (new Modifiers())->constrained(Builtin::Numeric, ['1000', '-1000']));
        self::assertInstanceOf(Invalid::class, (new Modifiers())->constrained(Builtin::Numeric, ['1001']));
        self::assertInstanceOf(Invalid::class, (new Modifiers())->constrained(Builtin::Numeric, ['3', '2', '1']));
        self::assertInstanceOf(Invalid::class, (new Modifiers())->constrained(Builtin::Varchar, ['0']));
        self::assertInstanceOf(Known::class, (new Modifiers())->constrained(Builtin::Varbit, ['83886080']));
        self::assertInstanceOf(Invalid::class, (new Modifiers())->constrained(Builtin::Text, ['1']));
        self::assertInstanceOf(Invalid::class, (new Modifiers())->constrained(Builtin::Bpchar, ['99999999999']));
    }

    public function testConstrainedReducesAPrecisionAboveSix(): void
    {
        $fact = (new Modifiers())->constrained(Builtin::Timestamptz, ['9']);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('timestamp(6) with time zone', $fact->descriptor->name());
        $interval = (new Modifiers())->constrained(Builtin::Interval, ['2']);
        self::assertInstanceOf(Known::class, $interval);
        self::assertSame('interval(2)', $interval->descriptor->name());
    }
}
