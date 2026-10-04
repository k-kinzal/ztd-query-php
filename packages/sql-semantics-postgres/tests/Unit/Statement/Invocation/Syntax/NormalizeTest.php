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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Normalize;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Normalize::class)]
#[Small]
final class NormalizeTest extends TestCase
{
    public function testOutputNameIsNormalize(): void
    {
        self::assertSame('normalize', (new Normalize(new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Normalize(new Constant(new StringConstant('x')), NormalForm::Nfd), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheForm(): void
    {
        $normalize = new Normalize(new Constant(new StringConstant('x')), NormalForm::Nfkd);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $normalize->render($out);
        self::assertSame('NORMALIZE(\'x\', NFKD)', (new Lexical())->join($out->pieces()));
    }
}
