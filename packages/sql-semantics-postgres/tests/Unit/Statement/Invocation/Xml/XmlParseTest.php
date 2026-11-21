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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlParse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlWhitespace;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

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
