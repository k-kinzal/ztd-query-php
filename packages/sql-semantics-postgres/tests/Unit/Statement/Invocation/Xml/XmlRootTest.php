<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlRoot;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlStandalone;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(XmlRoot::class)]
#[Small]
final class XmlRootTest extends TestCase
{
    public function testOutputNameIsXmlroot(): void
    {
        self::assertSame('xmlroot', (new XmlRoot(new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarFollowsTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlRoot(new Constant(new StringConstant('<a/>')), new Constant(new StringConstant('1.0'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Xml), Nullability::NotNull), $fact);
    }

    public function testRenderWritesNoValueAndStandalone(): void
    {
        $root = new XmlRoot(new Constant(new StringConstant('<a/>')), null, XmlStandalone::NoValue);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $root->render($out);
        self::assertSame('XMLROOT(\'<a/>\', VERSION NO VALUE, STANDALONE NO VALUE)', (new Lexical())->join($out->pieces()));
    }
}
