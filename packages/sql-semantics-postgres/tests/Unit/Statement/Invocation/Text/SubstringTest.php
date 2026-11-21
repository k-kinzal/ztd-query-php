<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Substring;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Substring::class)]
#[Small]
final class SubstringTest extends TestCase
{
    public function testRejectsNeitherStartNorCount(): void
    {
        $this->expectExceptionMessage('SUBSTRING takes FROM, FOR or both.');
        new Substring(new NullLiteral());
    }

    public function testRejectsCountFirstWithoutBoth(): void
    {
        $this->expectExceptionMessage('FOR is written before FROM only when both are written.');
        new Substring(new NullLiteral(), new Constant(new IntegerConstant('1')), null, true);
    }

    public function testOutputNameIsSubstring(): void
    {
        self::assertSame('substring', (new Substring(new NullLiteral(), new Constant(new IntegerConstant('1'))))->outputName()->value);
    }

    public function testDeriveScalarTypesTheCountOnlyFormAsAnIntegerCall(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Substring(new Constant(new StringConstant('abc')), null, new Constant(new StringConstant('2'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testRenderKeepsTheOrderWritten(): void
    {
        $substring = new Substring(new Constant(new StringConstant('abc')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1')), true);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $substring->render($out);
        self::assertSame('SUBSTRING(\'abc\' FOR 1 FROM 1)', (new Lexical())->join($out->pieces()));
    }
}
