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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlElement;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(XmlElement::class)]
#[Small]
final class XmlElementTest extends TestCase
{
    public function testOutputNameIsXmlelement(): void
    {
        self::assertSame('xmlelement', (new XmlElement(new Name('a')))->outputName()->value);
    }

    public function testDeriveScalarReportsAnUnnamedAttribute(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlElement(new Name('a'), [new XmlAttribute(new Constant(new IntegerConstant('1')))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Xml), Nullability::NotNull), $fact);
        self::assertEquals([new XmlProblem(XmlProblemKind::UnnamedAttribute)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesAttributesAndContent(): void
    {
        $element = new XmlElement(new Name('a'), [new XmlAttribute(new Constant(new IntegerConstant('1')), new Name('b'))], [new Constant(new StringConstant('c'))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $element->render($out);
        self::assertSame('XMLELEMENT(NAME a, XMLATTRIBUTES(1 AS b), \'c\')', (new Lexical())->join($out->pieces()));
    }
}
