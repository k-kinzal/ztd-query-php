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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\DocumentTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DocumentTest::class)]
#[Small]
final class DocumentTestTest extends TestCase
{
    public function testDeriveScalarReportsANonXmlOperand(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new DocumentTest(new Constant(new IntegerConstant('1')), false), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
    }

    public function testRenderWritesTheTest(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DocumentTest(new NullLiteral(), true))->render($out);
        self::assertSame('NULL IS NOT DOCUMENT', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANegation(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new DocumentTest(new Negation(new NullLiteral()), false);
    }
}
