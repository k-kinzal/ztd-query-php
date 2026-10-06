<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
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
#[Medium]
final class ConstantTest extends TestCase
{
    public function testBuiltinFollowsTheSizeOfAnInteger(): void
    {
        self::assertSame(Builtin::Int4, (new Constant(new IntegerConstant('2147483647')))->builtin());
        self::assertSame(Builtin::Int8, (new Constant(new NumericConstant('2147483648')))->builtin());
        self::assertSame(Builtin::Int8, (new Constant(new NumericConstant('0x7FFF_FFFF_FFFF_FFFF')))->builtin());
        self::assertSame(Builtin::Numeric, (new Constant(new NumericConstant('9223372036854775808')))->builtin());
    }

    public function testBuiltinReadsTheTextOfANumericConstantThroughTheFacade(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 0x1FFFFFFFFF, 1_000_000_000_000, 0001.50, 1e2');
        self::assertEquals([new Known(Builtin::Int8), new Known(Builtin::Int8), new Known(Builtin::Numeric), new Known(Builtin::Numeric)], [$query->field(0)->type, $query->field(1)->type, $query->field(2)->type, $query->field(3)->type]);
        self::assertSame('SELECT 0x1FFFFFFFFF, 1_000_000_000_000, 0001.50, 1e2', $query->toString());
    }

    public function testBuiltinOfTheOtherConstants(): void
    {
        self::assertSame(Builtin::Numeric, (new Constant(new NumericConstant('1.')))->builtin());
        self::assertSame(Builtin::Unknown, (new Constant(new StringConstant('1')))->builtin());
        self::assertSame(Builtin::Bit, (new Constant(new BitStringConstant(BitStringRadix::Binary, '1')))->builtin());
    }

    public function testIntegerValueReadsIntegersAndStringsThatSpellOne(): void
    {
        self::assertSame('10', (new Constant(new IntegerConstant('10')))->integerValue());
        self::assertSame('-5', (new Constant(new StringConstant(' -005 ')))->integerValue());
        self::assertNull((new Constant(new StringConstant('1.5')))->integerValue());
        self::assertNull((new Constant(new NumericConstant('1.')))->integerValue());
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
