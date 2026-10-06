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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\NullIf;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NullIf::class)]
#[Small]
final class NullIfTest extends TestCase
{
    public function testOutputNameIsNullif(): void
    {
        self::assertSame('nullif', (new NullIf(new NullLiteral(), new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarTakesTheTypeOfTheFirstOperand(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NullIf(new Constant(new IntegerConstant('1')), new Constant(new StringConstant('2'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::Nullable), $fact);
    }

    public function testDeriveScalarPromotesAStringFirstOperand(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NullIf(new Constant(new StringConstant('1')), new Constant(new IntegerConstant('1'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::Nullable), $fact);
    }

    public function testRenderWritesBothOperands(): void
    {
        $nullif = new NullIf(new Constant(new IntegerConstant('1')), new NullLiteral());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $nullif->render($out);
        self::assertSame('NULLIF(1, NULL)', (new Lexical())->join($out->pieces()));
    }
}
