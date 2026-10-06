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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\ArgumentSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NamedArgument::class)]
#[Small]
final class NamedArgumentTest extends TestCase
{
    public function testValueAnswersTheValuePassed(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        self::assertSame($value, (new NamedArgument(new Name('a'), $value))->value());
    }

    public function testNameAnswersTheParameterName(): void
    {
        self::assertSame('a', (new NamedArgument(new Name('a'), new NullLiteral()))->name()->value);
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new NamedArgument(new Name('a'), $value))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesTheArrowSpelling(): void
    {
        $argument = new NamedArgument(new Name('days'), new Constant(new IntegerConstant('1')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $argument->render($out);
        self::assertSame('days => 1', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheAssignmentSpellingAndQuotesAColumnNameKeyword(): void
    {
        $argument = new NamedArgument(new Name('coalesce'), new Constant(new IntegerConstant('1')), ArgumentSpelling::Assignment);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $argument->render($out);
        self::assertSame('"coalesce" := 1', (new Lexical())->join($out->pieces()));
    }
}
