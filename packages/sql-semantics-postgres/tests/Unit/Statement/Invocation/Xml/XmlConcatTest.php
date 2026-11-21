<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlConcat;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(XmlConcat::class)]
#[Small]
final class XmlConcatTest extends TestCase
{
    public function testRejectsNoValue(): void
    {
        $this->expectExceptionMessage('XMLCONCAT takes at least one value.');
        new XmlConcat([]);
    }

    public function testOutputNameIsXmlconcat(): void
    {
        self::assertSame('xmlconcat', (new XmlConcat([new NullLiteral()]))->outputName()->value);
    }

    public function testDeriveScalarIsNullableWhenEveryValueIs(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlConcat([new NullLiteral()]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Xml), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheValues(): void
    {
        $concat = new XmlConcat([new NullLiteral(), new NullLiteral()]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $concat->render($out);
        self::assertSame('XMLCONCAT(NULL, NULL)', (new Lexical())->join($out->pieces()));
    }
}
