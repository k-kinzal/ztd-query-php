<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Arithmetic::class)]
#[Small]
final class ArithmeticTest extends TestCase
{
    public function testArithmeticPromotesNumbers(): void
    {
        $typing = new Arithmetic();
        self::assertSame([Builtin::Int8, Builtin::Numeric, Builtin::Float8, Builtin::Float8, null], [$typing->arithmetic('+', Builtin::Int2, Builtin::Int8), $typing->arithmetic('%', Builtin::Int4, Builtin::Numeric), $typing->arithmetic('*', Builtin::Float4, Builtin::Numeric), $typing->arithmetic('^', Builtin::Int4, Builtin::Int4), $typing->arithmetic('%', Builtin::Float8, Builtin::Float8)]);
    }

    public function testConcatenationGivesTextWithAString(): void
    {
        self::assertSame([Builtin::Text, null], [(new Arithmetic())->concatenation('||', Builtin::Int4, Builtin::Varchar), (new Arithmetic())->concatenation('||', Builtin::Int4, Builtin::Int4)]);
    }

    public function testBitwiseKeepsTheIntegerType(): void
    {
        self::assertSame([Builtin::Int8, Builtin::Int2], [(new Arithmetic())->bitwise('&', Builtin::Int2, Builtin::Int8), (new Arithmetic())->bitwise('<<', Builtin::Int2, Builtin::Int4)]);
    }


    public function testScalingScalesAnInterval(): void
    {
        self::assertSame([Builtin::Interval, null], [(new Arithmetic())->scaling('/', Builtin::Interval, Builtin::Int4), (new Arithmetic())->scaling('/', Builtin::Int4, Builtin::Interval)]);
    }
}
