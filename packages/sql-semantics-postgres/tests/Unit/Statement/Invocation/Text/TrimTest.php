<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Trim;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\TrimSide;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Trim::class)]
#[Small]
final class TrimTest extends TestCase
{
    public function testRejectsNoString(): void
    {
        $this->expectExceptionMessage('TRIM takes at least one string.');
        new Trim(TrimSide::Both, null, []);
    }

    public function testOutputNameIsTheFunctionCalled(): void
    {
        self::assertSame('ltrim', (new Trim(TrimSide::Leading, null, [new NullLiteral()]))->outputName()->value);
    }

    public function testDeriveScalarPassesTheCharactersLast(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Trim(TrimSide::Trailing, new Constant(new StringConstant('x')), [new Constant(new StringConstant('axx'))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarDependsForTooManyStrings(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Trim(TrimSide::Both, new Constant(new StringConstant('x')), [new Constant(new StringConstant('a')), new Constant(new StringConstant('b'))]), $derivation->environment());
        self::assertInstanceOf(Dependent::class, $fact->type);
    }

    public function testRenderLeavesTheDefaultSideUnwritten(): void
    {
        $trim = new Trim(TrimSide::Both, new Constant(new StringConstant('x')), [new Constant(new StringConstant('xax'))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $trim->render($out);
        (new Trim(TrimSide::Leading, null, [new Constant(new StringConstant('xax'))]))->render($out);
        self::assertSame('TRIM(\'x\' FROM \'xax\') TRIM(LEADING \'xax\')', (new Lexical())->join($out->pieces()));
    }
}
