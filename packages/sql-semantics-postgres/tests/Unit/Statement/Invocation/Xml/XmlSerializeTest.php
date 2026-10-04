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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlSerialize;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(XmlSerialize::class)]
#[Small]
final class XmlSerializeTest extends TestCase
{
    public function testOutputNameIsXmlserialize(): void
    {
        self::assertSame('xmlserialize', (new XmlSerialize(XmlOption::Content, new NullLiteral(), new TypeName(new NamedDesignation(new DottedName([new Name('text')])))))->outputName()->value);
    }

    public function testDeriveScalarIsTheCharacterType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlSerialize(XmlOption::Content, new Constant(new StringConstant('<a/>')), new TypeName(new NamedDesignation(new DottedName([new Name('text')])))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarReportsATypeThatIsNotACharacterType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlSerialize(XmlOption::Content, new Constant(new StringConstant('<a/>')), new TypeName(new KeywordDesignation(TypeKeyword::Integer))), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame('cannot cast XMLSERIALIZE result to integer', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheIndentation(): void
    {
        $serialize = new XmlSerialize(XmlOption::Document, new Constant(new StringConstant('<a/>')), new TypeName(new NamedDesignation(new DottedName([new Name('text')]))), XmlIndent::NoIndent);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $serialize->render($out);
        self::assertSame('XMLSERIALIZE(DOCUMENT \'<a/>\' AS text NO INDENT)', (new Lexical())->join($out->pieces()));
    }
}
