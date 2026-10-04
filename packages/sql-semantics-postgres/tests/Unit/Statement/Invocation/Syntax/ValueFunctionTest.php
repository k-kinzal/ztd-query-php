<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ValueFunction::class)]
#[Small]
final class ValueFunctionTest extends TestCase
{
    public function testRejectsAPrecisionOnANameFunction(): void
    {
        $this->expectExceptionMessage('Only the time functions take a precision.');
        new ValueFunction(ValueFunctionKind::User, new IntegerConstant('1'));
    }

    public function testOutputNameIsTheKeywordInLowerCase(): void
    {
        self::assertSame('current_catalog', (new ValueFunction(ValueFunctionKind::CurrentCatalog))->outputName()->value);
    }

    public function testDeriveScalarReducesThePrecisionToSix(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ValueFunction(ValueFunctionKind::CurrentTimestamp, new IntegerConstant('9')), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(new Parameterized(Builtin::Timestamptz, 6)), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarMakesCurrentSchemaNullable(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ValueFunction(ValueFunctionKind::CurrentSchema), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Name), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheKeywordAndThePrecision(): void
    {
        $value = new ValueFunction(ValueFunctionKind::Localtimestamp, new IntegerConstant('2'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $value->render($out);
        self::assertSame('LOCALTIMESTAMP(2)', (new Lexical())->join($out->pieces()));
    }
}
