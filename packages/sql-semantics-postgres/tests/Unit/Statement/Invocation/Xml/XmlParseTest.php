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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlParse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlWhitespace;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(XmlParse::class)]
#[Small]
final class XmlParseTest extends TestCase
{
    public function testOutputNameIsXmlparse(): void
    {
        self::assertSame('xmlparse', (new XmlParse(XmlOption::Content, new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsNullWhenTheValueIs(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlParse(XmlOption::Content, new NullLiteral()), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Xml), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheWhitespaceOption(): void
    {
        $parse = new XmlParse(XmlOption::Document, new Constant(new StringConstant('<a/>')), XmlWhitespace::Strip);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $parse->render($out);
        self::assertSame('XMLPARSE(DOCUMENT \'<a/>\' STRIP WHITESPACE)', (new Lexical())->join($out->pieces()));
    }
}
