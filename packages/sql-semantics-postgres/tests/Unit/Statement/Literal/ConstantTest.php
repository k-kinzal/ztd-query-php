<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Constant::class)]
#[Small]
final class ConstantTest extends TestCase
{
    public function testBuiltinFollowsTheSizeOfAnInteger(): void
    {
        self::assertSame(Builtin::Int4, (new Constant(new IntegerConstant('2147483647')))->builtin());
        self::assertSame(Builtin::Int8, (new Constant(new IntegerConstant('2147483648')))->builtin());
        self::assertSame(Builtin::Int8, (new Constant(new IntegerConstant('9223372036854775807')))->builtin());
        self::assertSame(Builtin::Numeric, (new Constant(new IntegerConstant('9223372036854775808')))->builtin());
    }

    public function testBuiltinOfTheOtherConstants(): void
    {
        self::assertSame(Builtin::Numeric, (new Constant(new NumericConstant('1')))->builtin());
        self::assertSame(Builtin::Unknown, (new Constant(new StringConstant('1')))->builtin());
        self::assertSame(Builtin::Bit, (new Constant(new BitStringConstant(BitStringRadix::Binary, '1')))->builtin());
    }

    public function testIntegerValueReadsIntegersAndStringsThatSpellOne(): void
    {
        self::assertSame('10', (new Constant(new IntegerConstant('10')))->integerValue());
        self::assertSame('-5', (new Constant(new StringConstant(' -005 ')))->integerValue());
        self::assertNull((new Constant(new StringConstant('1.5')))->integerValue());
        self::assertNull((new Constant(new NumericConstant('1')))->integerValue());
    }

    public function testOutputNameIsNone(): void
    {
        self::assertNull((new Constant(new IntegerConstant('1')))->outputName());
    }

    public function testDeriveScalarIsTheCatalogTypeAndNeverNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Constant(new StringConstant('a')), $derivation->environment());
        self::assertEquals(new Known(Builtin::Unknown), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Constant(new StringConstant('a')))->render($out);
        self::assertSame("'a'", (new Lexical())->join($out->pieces()));
    }
}
