<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(PositionalArgument::class)]
#[Small]
final class PositionalArgumentTest extends TestCase
{
    public function testValueAnswersTheValuePassed(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        self::assertSame($value, (new PositionalArgument($value))->value());
    }

    public function testNameIsNull(): void
    {
        self::assertNull((new PositionalArgument(new NullLiteral()))->name());
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new PositionalArgument($value))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesTheValue(): void
    {
        $argument = new PositionalArgument(new Constant(new IntegerConstant('1')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $argument->render($out);
        self::assertSame('1', (new Lexical())->join($out->pieces()));
    }
}
