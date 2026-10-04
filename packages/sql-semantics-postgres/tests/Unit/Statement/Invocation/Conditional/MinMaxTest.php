<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMax;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMaxKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(MinMax::class)]
#[Small]
final class MinMaxTest extends TestCase
{
    public function testRejectsNoValue(): void
    {
        $this->expectExceptionMessage('GREATEST and LEAST take at least one value.');
        new MinMax(MinMaxKind::Least, []);
    }

    public function testOutputNameIsTheFunction(): void
    {
        self::assertSame('least', (new MinMax(MinMaxKind::Least, [new NullLiteral()]))->outputName()->value);
    }

    public function testDeriveScalarIsTheCommonType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new MinMax(MinMaxKind::Greatest, [new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('9999999999'))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int8), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $greatest = new MinMax(MinMaxKind::Greatest, [new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1'))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $greatest->render($out);
        self::assertSame('GREATEST(1, 1)', (new Lexical())->join($out->pieces()));
    }
}
