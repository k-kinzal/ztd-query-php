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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(XmlPassing::class)]
#[Small]
final class XmlPassingTest extends TestCase
{
    public function testRejectsADocumentThatIsNotPrimary(): void
    {
        $this->expectExceptionMessage('The document passed is a primary expression; group it.');
        new XmlPassing(new BinaryOperation(new OperatorName(new Name('=')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1'))));
    }

    public function testDeriveClauseDerivesTheDocument(): void
    {
        $document = new Constant(new StringConstant('<a/>'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new XmlPassing($document))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($document));
    }

    public function testRenderWritesPassing(): void
    {
        $passing = new XmlPassing(new Constant(new StringConstant('<a/>')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $passing->render($out);
        self::assertSame('PASSING \'<a/>\'', (new Lexical())->join($out->pieces()));
    }
}
