<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\SimilarSubstring;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SimilarSubstring::class)]
#[Small]
final class SimilarSubstringTest extends TestCase
{
    public function testOutputNameIsSubstring(): void
    {
        self::assertSame('substring', (new SimilarSubstring(new NullLiteral(), new NullLiteral(), new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new SimilarSubstring(new Constant(new StringConstant('abc')), new Constant(new StringConstant('a')), new Constant(new StringConstant('#'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheKeywords(): void
    {
        $substring = new SimilarSubstring(new Constant(new StringConstant('abc')), new Constant(new StringConstant('a')), new Constant(new StringConstant('#')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $substring->render($out);
        self::assertSame('SUBSTRING(\'abc\' SIMILAR \'a\' ESCAPE \'#\')', (new Lexical())->join($out->pieces()));
    }
}
