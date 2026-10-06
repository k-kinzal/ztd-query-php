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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlForest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(XmlForest::class)]
#[Small]
final class XmlForestTest extends TestCase
{
    public function testRejectsNoValue(): void
    {
        $this->expectExceptionMessage('XMLFOREST takes at least one value.');
        new XmlForest([]);
    }

    public function testOutputNameIsXmlforest(): void
    {
        self::assertSame('xmlforest', (new XmlForest([new XmlAttribute(new NullLiteral(), new Name('a'))]))->outputName()->value);
    }

    public function testDeriveScalarReportsAnUnnamedValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlForest([new XmlAttribute(new Constant(new IntegerConstant('1')))]), $derivation->environment());
        self::assertEquals([new XmlProblem(XmlProblemKind::UnnamedElement)], $derivation->facts()->diagnostics);
        self::assertEquals(new Known(Builtin::Xml), $fact->type);
    }

    public function testRenderWritesTheValues(): void
    {
        $forest = new XmlForest([new XmlAttribute(new Constant(new IntegerConstant('1')), new Name('a'))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $forest->render($out);
        self::assertSame('XMLFOREST(1 AS a)', (new Lexical())->join($out->pieces()));
    }
}
