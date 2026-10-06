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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlExists;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(XmlExists::class)]
#[Small]
final class XmlExistsTest extends TestCase
{
    public function testRejectsAPathThatIsNotPrimary(): void
    {
        $this->expectExceptionMessage('The path of XMLEXISTS is a primary expression; group it.');
        new XmlExists(new BinaryOperation(new OperatorName(new Name('=')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1'))), new XmlPassing(new NullLiteral()));
    }

    public function testOutputNameIsXmlexists(): void
    {
        self::assertSame('xmlexists', (new XmlExists(new NullLiteral(), new XmlPassing(new NullLiteral())))->outputName()->value);
    }

    public function testDeriveScalarIsBoolean(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new XmlExists(new Constant(new StringConstant('//a')), new XmlPassing(new Constant(new StringConstant('<a/>')))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Bool), Nullability::NotNull), $fact);
    }

    public function testRenderWritesThePassingClause(): void
    {
        $exists = new XmlExists(new Constant(new StringConstant('//a')), new XmlPassing(new Constant(new StringConstant('<a/>'))));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $exists->render($out);
        self::assertSame('XMLEXISTS(\'//a\' PASSING \'<a/>\')', (new Lexical())->join($out->pieces()));
    }
}
