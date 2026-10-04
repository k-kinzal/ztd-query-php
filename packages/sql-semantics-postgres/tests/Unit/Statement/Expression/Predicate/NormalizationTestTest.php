<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NormalizationTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(NormalizationTest::class)]
#[Small]
final class NormalizationTestTest extends TestCase
{
    public function testDeriveScalarIsBooleanForText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NormalizationTest(new Constant(new StringConstant('a')), false), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testRenderWritesTheForm(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NormalizationTest(new NullLiteral(), true, NormalForm::Nfd))->render($out);
        self::assertSame('NULL IS NOT NFD NORMALIZED', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANegation(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new NormalizationTest(new Negation(new NullLiteral()), false);
    }
}
